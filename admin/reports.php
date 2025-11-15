<?php
// reports.php
require_once '../config.php';
require_once '../session.php';
requireLogin();

$db = new Database();
$conn = $db->connect();

// Determine report type
$report_type = isset($_GET['type']) ? $_GET['type'] : 'attendance';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
// CSV export
$export = isset($_GET['export']) ? $_GET['export'] : '';

// Initialize data array
$data = [];

// Build query based on report type
switch($report_type) {
    case 'attendance':
        $query = "SELECT a.id, a.member_id, m.first_name, m.last_name, a.check_in_time, a.check_out_time, a.date, a.created_at
                  FROM attendance a
                  JOIN members m ON a.member_id = m.id
                  WHERE 1=1";
        $params = [];
        if ($start_date) { $query .= " AND a.date >= ?"; $params[] = $start_date; }
        if ($end_date) { $query .= " AND a.date <= ?"; $params[] = $end_date; }
        $query .= " ORDER BY a.date DESC";
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;

    case 'subscriptions':
        $query = "SELECT s.id, s.member_id, m.first_name, m.last_name, s.plan_id, p.plan_name, s.start_date, s.end_date, s.amount_paid, s.payment_method, s.status, s.created_at
                  FROM subscriptions s
                  JOIN members m ON s.member_id = m.id
                  JOIN membership_plans p ON s.plan_id = p.id
                  WHERE 1=1";
        $params = [];
        if ($start_date) { $query .= " AND s.start_date >= ?"; $params[] = $start_date; }
        if ($end_date) { $query .= " AND s.end_date <= ?"; $params[] = $end_date; }
        $query .= " ORDER BY s.start_date DESC";
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;

    case 'payments':
        $query = "SELECT p.id, p.member_id, m.first_name, m.last_name, p.subscription_id, p.amount, p.payment_date, p.payment_method, p.description, p.created_by, p.created_at
                  FROM payments p
                  JOIN members m ON p.member_id = m.id
                  WHERE 1=1";
        $params = [];
        if ($start_date) { $query .= " AND p.payment_date >= ?"; $params[] = $start_date; }
        if ($end_date) { $query .= " AND p.payment_date <= ?"; $params[] = $end_date; }
        $query .= " ORDER BY p.payment_date DESC";
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;

    case 'revenue_summary':
        // Revenue totals and breakdown by method
        $query = "SELECT SUM(p.amount) AS total_revenue, p.payment_method, DATE_FORMAT(p.payment_date, '%Y-%m') AS month
                  FROM payments p
                  WHERE 1=1";
        $params = [];
        if ($start_date) { $query .= " AND p.payment_date >= ?"; $params[] = $start_date; }
        if ($end_date) { $query .= " AND p.payment_date <= ?"; $params[] = $end_date; }
        $query .= " GROUP BY month, p.payment_method ORDER BY month DESC";
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;

    case 'plan_distribution':
        $query = "SELECT p.id, p.plan_name, COUNT(DISTINCT s.member_id) AS members_count
                  FROM membership_plans p
                  LEFT JOIN subscriptions s ON s.plan_id = p.id AND s.status = 'Active'
                  GROUP BY p.id ORDER BY members_count DESC";
        $stmt = $conn->query($query);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;

        

    case 'members_status':
        // Summary of member statuses
        $query = "SELECT status, COUNT(*) as count FROM members GROUP BY status";
        $stmt = $conn->query($query);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;

    case 'new_members':
        $query = "SELECT id, member_id, first_name, last_name, email, phone, status, created_at
                  FROM members WHERE 1=1";
        $params = [];
        if ($start_date) { $query .= " AND created_at >= ?"; $params[] = $start_date; }
        if ($end_date) { $query .= " AND created_at <= ?"; $params[] = $end_date; }
        $query .= " ORDER BY created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;

    case 'attendance_summary':
        // Total check-ins and top attendees
        $topSql = "SELECT m.id, m.member_id, m.first_name, m.last_name, COUNT(a.id) AS checkins
                   FROM attendance a
                   JOIN members m ON a.member_id = m.id
                   WHERE 1=1";
        $params = [];
        if ($start_date) { $topSql .= " AND a.date >= ?"; $params[] = $start_date; }
        if ($end_date) { $topSql .= " AND a.date <= ?"; $params[] = $end_date; }
        $topSql .= " GROUP BY m.id ORDER BY checkins DESC LIMIT 25";
        $stmt = $conn->prepare($topSql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
}

// Prepare chart data for supported report types (after $data is ready)
$chart = [];
if ($report_type == 'revenue_summary' && !empty($data)) {
    $monthly = [];
    foreach ($data as $r) {
        $m = $r['month'];
        $monthly[$m] = isset($monthly[$m]) ? $monthly[$m] + $r['total_revenue'] : $r['total_revenue'];
    }
    // sort by month asc
    ksort($monthly);
    $chart['labels'] = array_keys($monthly);
    $chart['values'] = array_values($monthly);
}

if ($report_type == 'plan_distribution' && !empty($data)) {
    $chart['labels'] = array_column($data, 'plan_name');
    $chart['values'] = array_column($data, 'members_count');
}

if ($report_type == 'members_status' && !empty($data)) {
    $chart['labels'] = array_column($data, 'status');
    $chart['values'] = array_column($data, 'count');
}

if ($report_type == 'attendance_summary' && !empty($data)) {
    $chart['labels'] = array_map(function($r){return $r['member_id'].' - '.$r['first_name'].' '.$r['last_name'];}, $data);
    $chart['values'] = array_column($data, 'checkins');
}

if ($report_type == 'new_members' && !empty($data)) {
    $membersByMonth = [];
    foreach ($data as $r) {
        $m = date('Y-m', strtotime($r['created_at']));
        $membersByMonth[$m] = isset($membersByMonth[$m]) ? $membersByMonth[$m] + 1 : 1;
    }
    ksort($membersByMonth);
    $chart['labels'] = array_keys($membersByMonth);
    $chart['values'] = array_values($membersByMonth);
}

// CSV export handling (for same report types above)
if ($export === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="report_'. $report_type .'.csv"');
    $output = fopen('php://output','w');
    if (!empty($data)) {
        // use associative array keys from first row for headers
        fputcsv($output, array_keys($data[0]));
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reports - Gym Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
    :root { --primary-color: #ff6b6b; --secondary-color: #4ecdc4; --dark-color: #2c3e50; }
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f5f5f5; }
    .sidebar { background: linear-gradient(135deg, var(--dark-color) 0%, #34495e 100%); min-height: 100vh; position: fixed; top:0; left:0; width:250px; padding-top:20px; color:white; }
    .sidebar .logo { text-align:center; padding:20px; border-bottom:1px solid rgba(255,255,255,0.1); }
    .sidebar .logo i { font-size:3rem; color: var(--primary-color); }
    .sidebar .nav-link { color: rgba(255,255,255,0.8); padding:15px 25px; border-left:3px solid transparent; transition: all 0.3s; }
    .sidebar .nav-link:hover, .sidebar .nav-link.active { background: rgba(255,255,255,0.1); color:white; border-left-color: var(--primary-color); }
    .main-content { margin-left:250px; padding:20px; }
    .card { border:none; border-radius:15px; box-shadow:0 5px 15px rgba(0,0,0,0.08); }
    .card-header { background:white; border-bottom:2px solid var(--primary-color); font-weight:600; border-radius:15px 15px 0 0 !important; }
    .chart-container { position:relative; width:100%; height:400px; margin-top:30px; }
    .chart-container canvas { max-height:400px; }
</style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo"><i class="fas fa-dumbbell"></i><h4 class="mt-2">GYM Manager</h4></div>
        <nav class="nav flex-column mt-4">
            <a class="nav-link" href="index.php"><i class="fas fa-home me-2"></i> Dashboard</a>
            <a class="nav-link" href="membership.php"><i class="fas fa-users me-2"></i> Members</a>
            <a class="nav-link" href="attendance.php"><i class="fas fa-clipboard-check me-2"></i> Attendance</a>
            <a class="nav-link" href="subscriptions.php"><i class="fas fa-id-card me-2"></i> Subscriptions</a>
            <a class="nav-link" href="payments.php"><i class="fas fa-dollar-sign me-2"></i> Payments</a>
            <a class="nav-link" href="plans.php"><i class="fas fa-list me-2"></i> Membership Plans</a>
            <a class="nav-link" href="discounts.php"><i class="fas fa-tag me-2"></i> Discounts</a>
            <a class="nav-link active" href="reports.php"><i class="fas fa-chart-bar me-2"></i> Reports</a>
            <hr style="border-color: rgba(255,255,255,0.2);">
            <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-chart-bar me-2"></i>Reports</h2>
        </div>

        <!-- Report Type Selector -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Report Type</label>
                        <select class="form-select" name="type" onchange="this.form.submit()">
                            <option value="attendance" <?= $report_type=='attendance'?'selected':'' ?>>Attendance</option>
                            <option value="subscriptions" <?= $report_type=='subscriptions'?'selected':'' ?>>Subscriptions</option>
                            <option value="payments" <?= $report_type=='payments'?'selected':'' ?>>Payments</option>
                            <option value="revenue_summary" <?= $report_type=='revenue_summary'?'selected':'' ?>>Revenue Summary</option>
                            <option value="plan_distribution" <?= $report_type=='plan_distribution'?'selected':'' ?>>Plan Distribution</option>
                            <option value="members_status" <?= $report_type=='members_status'?'selected':'' ?>>Members Status</option>
                            <option value="new_members" <?= $report_type=='new_members'?'selected':'' ?>>New Members</option>
                            <option value="attendance_summary" <?= $report_type=='attendance_summary'?'selected':'' ?>>Attendance Summary</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control" name="start_date" value="<?= $start_date ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-control" name="end_date" value="<?= $end_date ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Print Button -->
        <div class="mb-3 text-end">
            <button class="btn btn-outline-primary" onclick="printReport()">
                <i class="fas fa-print me-1"></i>Print Report
            </button>
            <a class="btn btn-outline-secondary ms-2" href="?type=<?= $report_type ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&export=csv">
                <i class="fas fa-download me-1"></i>Export CSV
            </a>
        </div>

        <!-- Report Table -->
        <div class="card">
            <div class="card-header"><i class="fas fa-table me-2"></i><?= ucfirst($report_type) ?> Report</div>
            <div class="card-body table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <?php if($report_type=='attendance'): ?>
                                <th>#</th><th>Member ID</th><th>Name</th><th>Check In</th><th>Check Out</th><th>Date</th><th>Created At</th>
                            <?php elseif($report_type=='subscriptions'): ?>
                                <th>#</th><th>Member ID</th><th>Name</th><th>Plan</th><th>Start Date</th><th>End Date</th><th>Amount Paid</th><th>Payment Method</th><th>Status</th><th>Created At</th>
                            <?php elseif ($report_type=='revenue_summary'): ?>
                                <th>#</th><th>Month</th><th>Payment Method</th><th>Total</th>
                            <?php elseif ($report_type=='plan_distribution'): ?>
                                <th>#</th><th>Plan</th><th>Active Members</th>
                            <?php elseif ($report_type=='members_status'): ?>
                                <th>#</th><th>Status</th><th>Count</th>
                            <?php elseif ($report_type=='new_members'): ?>
                                <th>#</th><th>Member ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Status</th><th>Created At</th>
                            <?php elseif ($report_type=='attendance_summary'): ?>
                                <th>#</th><th>Member ID</th><th>Name</th><th>Checkins</th>
                            <?php else: ?>
                                <th>#</th><th>Member ID</th><th>Name</th><th>Subscription ID</th><th>Amount</th><th>Payment Date</th><th>Method</th><th>Description</th><th>Created By</th><th>Created At</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($data)): ?>
                            <?php foreach($data as $index => $row): ?>
                                <tr>
                                    <?php if($report_type=='attendance'): ?>
                                        <td><?= $index+1 ?></td>
                                        <td><?= $row['member_id'] ?></td>
                                        <td><?= $row['first_name'].' '.$row['last_name'] ?></td>
                                        <td><?= $row['check_in_time'] ?></td>
                                        <td><?= $row['check_out_time'] ?></td>
                                        <td><?= $row['date'] ?></td>
                                        <td><?= $row['created_at'] ?></td>
                                    <?php elseif($report_type=='subscriptions'): ?>
                                        <td><?= $index+1 ?></td>
                                        <td><?= $row['member_id'] ?></td>
                                        <td><?= $row['first_name'].' '.$row['last_name'] ?></td>
                                        <td><?= $row['plan_name'] ?></td>
                                        <td><?= $row['start_date'] ?></td>
                                        <td><?= $row['end_date'] ?></td>
                                        <td>$<?= number_format($row['amount_paid'],2) ?></td>
                                        <td><?= $row['payment_method'] ?></td>
                                        <td><?= $row['status'] ?></td>
                                        <td><?= $row['created_at'] ?></td>
                                    <?php elseif($report_type=='payments'): ?>
                                        <td><?= $index+1 ?></td>
                                        <td><?= $row['member_id'] ?></td>
                                        <td><?= $row['first_name'].' '.$row['last_name'] ?></td>
                                        <td><?= $row['subscription_id'] ?></td>
                                        <td>$<?= number_format($row['amount'],2) ?></td>
                                        <td><?= $row['payment_date'] ?></td>
                                        <td><?= $row['payment_method'] ?></td>
                                        <td><?= $row['description'] ?></td>
                                        <td><?= $row['created_by'] ?></td>
                                        <td><?= $row['created_at'] ?></td>
                                    <?php elseif ($report_type=='revenue_summary'): ?>
                                        <td><?= $index+1 ?></td>
                                        <td><?= $row['month'] ?></td>
                                        <td><?= $row['payment_method'] ?></td>
                                        <td>$<?= number_format($row['total_revenue'],2) ?></td>
                                    <?php elseif ($report_type=='plan_distribution'): ?>
                                        <td><?= $index+1 ?></td>
                                        <td><?= $row['plan_name'] ?></td>
                                        <td><?= $row['members_count'] ?></td>
                                    <?php elseif ($report_type=='members_status'): ?>
                                        <td><?= $index+1 ?></td>
                                        <td><?= $row['status'] ?></td>
                                        <td><?= $row['count'] ?></td>
                                    <?php elseif ($report_type=='new_members'): ?>
                                        <td><?= $index+1 ?></td>
                                        <td><?= $row['member_id'] ?></td>
                                        <td><?= $row['first_name'].' '.$row['last_name'] ?></td>
                                        <td><?= $row['email'] ?></td>
                                        <td><?= $row['phone'] ?></td>
                                        <td><?= $row['status'] ?></td>
                                        <td><?= $row['created_at'] ?></td>
                                    <?php elseif ($report_type=='attendance_summary'): ?>
                                        <td><?= $index+1 ?></td>
                                        <td><?= $row['member_id'] ?></td>
                                        <td><?= $row['first_name'].' '.$row['last_name'] ?></td>
                                        <td><?= $row['checkins'] ?></td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="20" class="text-center text-muted">No records found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Additional information for summary reports -->
    <?php if ($report_type == 'revenue_summary' && !empty($data)): ?>
        <div class="main-content mt-3">
            <div class="card">
                <div class="card-header">Revenue Totals</div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <thead>
                            <tr><th>Month</th><th>Payment Method</th><th>Total</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($data as $row): ?>
                            <tr>
                                <td><?= $row['month'] ?></td>
                                <td><?= $row['payment_method'] ?></td>
                                <td>$<?= number_format($row['total_revenue'],2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="text-end"><a class="btn btn-outline-secondary" href="?type=<?= $report_type ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&export=csv">Export CSV</a></div>
                    <div class="chart-container"><canvas id="revenueChart"></canvas></div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($report_type == 'plan_distribution' && !empty($data)): ?>
        <div class="main-content mt-3">
            <div class="card">
                <div class="card-header">Plan Distribution</div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <thead><tr><th>Plan</th><th>Active Members</th></tr></thead>
                        <tbody>
                            <?php foreach($data as $row): ?>
                            <tr>
                                <td><?= $row['plan_name'] ?></td>
                                <td><?= $row['members_count'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="text-end"><a class="btn btn-outline-secondary" href="?type=<?= $report_type ?>&export=csv">Export CSV</a></div>
                    <div class="chart-container"><canvas id="planDistributionChart"></canvas></div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($report_type == 'members_status' && !empty($data)): ?>
        <div class="main-content mt-3">
            <div class="card">
                <div class="card-header">Members Status</div>
                <div class="card-body">
                    <table class="table table-bordered"><thead><tr><th>Status</th><th>Count</th></tr></thead><tbody>
                    <?php foreach($data as $row): ?>
                        <tr><td><?= $row['status'] ?></td><td><?= $row['count'] ?></td></tr>
                    <?php endforeach; ?>
                    </tbody></table>
                </div>
                <div class="chart-container"><canvas id="membersStatusChart"></canvas></div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($report_type == 'new_members' && !empty($data)): ?>
        <div class="main-content mt-3">
            <div class="card">
                <div class="card-header">New Members</div>
                <div class="card-body">
                    <div class="text-end"><a class="btn btn-outline-secondary" href="?type=<?= $report_type ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&export=csv">Export CSV</a></div>
                    <div class="chart-container"><canvas id="newMembersChart"></canvas></div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($report_type == 'attendance_summary' && !empty($data)): ?>
        <div class="main-content mt-3">
            <div class="card">
                <div class="card-header">Top Attendees</div>
                <div class="card-body">
                    <div class="text-end"><a class="btn btn-outline-secondary" href="?type=<?= $report_type ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&export=csv">Export CSV</a></div>
                    <div class="chart-container"><canvas id="attendanceSummaryChart"></canvas></div>
                </div>
            </div>
        </div>
    <?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.umd.min.js"></script>
<script>
// Chart building for reports
const chartData = <?= json_encode(isset($chart) ? $chart : ['labels'=>[], 'values'=>[]]) ?>;
const reportType = '<?= $report_type ?>';
// Helper to generate random colors
function generateColors(n) {
    const colors = [];
    for (let i = 0; i < n; i++) {
        const r = Math.floor(Math.random()*200)+30;
        const g = Math.floor(Math.random()*200)+30;
        const b = Math.floor(Math.random()*200)+30;
        colors.push(`rgba(${r}, ${g}, ${b}, 0.7)`);
    }
    return colors;
}

document.addEventListener('DOMContentLoaded', function(){
    if (!chartData || !chartData.labels || chartData.labels.length === 0) return;

    if (reportType === 'revenue_summary') {
        const ctx = document.getElementById('revenueChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Revenue',
                        data: chartData.values,
                        backgroundColor: 'rgba(78, 115, 223, 0.1)',
                        borderColor: 'rgba(78, 115, 223, 1)',
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: {responsive:true, maintainAspectRatio:false}
            });
        }
    }

    if (reportType === 'plan_distribution') {
        const ctx = document.getElementById('planDistributionChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: chartData.labels,
                    datasets: [{ data: chartData.values, backgroundColor: generateColors(chartData.labels.length) }]
                },
                options: {responsive:true, maintainAspectRatio:false}
            });
        }
    }

    if (reportType === 'members_status') {
        const ctx = document.getElementById('membersStatusChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'doughnut',
                data: { labels: chartData.labels, datasets: [{ data: chartData.values, backgroundColor: generateColors(chartData.labels.length) }] },
                options: {responsive:true, maintainAspectRatio:false}
            });
        }
    }

    if (reportType === 'attendance_summary') {
        const ctx = document.getElementById('attendanceSummaryChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'bar',
                data: { labels: chartData.labels, datasets: [{ label: 'Check-ins', data: chartData.values, backgroundColor: 'rgba(102, 178, 255, 0.8)' }] },
                options: {responsive:true, maintainAspectRatio:false, scales: {y: {beginAtZero:true}}}
            });
        }
    }

    if (reportType === 'new_members') {
        const ctx = document.getElementById('newMembersChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'line',
                data: { labels: chartData.labels, datasets: [{ label: 'New Members', data: chartData.values, borderColor: 'rgba(99, 102, 241, 1)', backgroundColor: 'rgba(99, 102, 241, 0.1)', fill: true }] },
                options: {responsive:true, maintainAspectRatio:false}
            });
        }
    }
});
</script>
<script>
// Print function
function printReport() {
    var printWindow = window.open('', '', 'height=600,width=1000');
    var tableHTML = document.querySelector('.card-body.table-responsive').innerHTML;
    printWindow.document.write('<html><head><title>Printable Report</title>');
    printWindow.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">');
    printWindow.document.write('<style>body{font-family:Arial,sans-serif; padding:20px;} table{width:100%; border-collapse:collapse;} th, td{border:1px solid #ccc; padding:8px; text-align:left;} th{background:#f2f2f2;}</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write('<h4><?= ucfirst($report_type) ?> Report</h4>');
    printWindow.document.write(tableHTML);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.print();
}
</script>
</body>
</html>
