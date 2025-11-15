<?php
// subscriptions.php
require_once '../config.php';
require_once '../session.php';
requireLogin();

$db = new Database();
$conn = $db->connect();

// Filters
$member_id = isset($_GET['member_id']) ? $_GET['member_id'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Fetch members for filter dropdown
$membersStmt = $conn->query("SELECT id, first_name, last_name FROM members ORDER BY first_name ASC");
$members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch plans for reference
$plansStmt = $conn->query("SELECT id, plan_name FROM membership_plans");
$plans = $plansStmt->fetchAll(PDO::FETCH_KEY_PAIR); // id => plan_name

// Build query
$query = "SELECT s.id, s.member_id, s.plan_id, s.start_date, s.end_date, s.amount_paid, s.payment_method, s.status, s.created_at,
                 m.first_name, m.last_name, p.price AS plan_price
          FROM subscriptions s
          JOIN members m ON s.member_id = m.id
          JOIN membership_plans p ON s.plan_id = p.id
          WHERE 1=1";
$params = [];

if ($member_id) {
    $query .= " AND s.member_id = ?";
    $params[] = $member_id;
}

if ($status_filter) {
    $query .= " AND s.status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY s.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Subscriptions - Gym Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
    :root {
        --primary-color: #ff6b6b;
        --secondary-color: #4ecdc4;
        --dark-color: #2c3e50;
    }
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: #f5f5f5;
    }
    .sidebar {
        background: linear-gradient(135deg, var(--dark-color) 0%, #34495e 100%);
        min-height: 100vh;
        position: fixed;
        top: 0;
        left: 0;
        width: 250px;
        padding-top: 20px;
        color: white;
    }
    .sidebar .logo {
        text-align: center;
        padding: 20px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    .sidebar .logo i {
        font-size: 3rem;
        color: var(--primary-color);
    }
    .sidebar .nav-link {
        color: rgba(255,255,255,0.8);
        padding: 15px 25px;
        border-left: 3px solid transparent;
        transition: all 0.3s;
    }
    .sidebar .nav-link:hover,
    .sidebar .nav-link.active {
        background: rgba(255,255,255,0.1);
        color: white;
        border-left-color: var(--primary-color);
    }
    .main-content {
        margin-left: 250px;
        padding: 20px;
    }
    .card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    }
    .card-header {
        background: white;
        border-bottom: 2px solid var(--primary-color);
        font-weight: 600;
        border-radius: 15px 15px 0 0 !important;
    }
    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
    }
    .btn-primary:hover {
        background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
    }
</style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <i class="fas fa-dumbbell"></i>
            <h4 class="mt-2">GYM Manager</h4>
        </div>
        <nav class="nav flex-column mt-4">
            <a class="nav-link" href="index.php">
                <i class="fas fa-home me-2"></i> Dashboard
            </a>
            <a class="nav-link" href="membership.php">
                <i class="fas fa-users me-2"></i> Members
            </a>
            <a class="nav-link" href="attendance.php">
                <i class="fas fa-clipboard-check me-2"></i> Attendance
            </a>
            <a class="nav-link active" href="subscriptions.php">
                <i class="fas fa-id-card me-2"></i> Subscriptions
            </a>
            <a class="nav-link" href="payments.php">
                <i class="fas fa-dollar-sign me-2"></i> Payments
            </a>
            <a class="nav-link" href="plans.php">
                <i class="fas fa-list me-2"></i> Membership Plans
            </a>
            <a class="nav-link" href="reports.php">
                <i class="fas fa-chart-bar me-2"></i> Reports
            </a>

            <?php if (isAdmin()): ?>
            <a class="nav-link" href="admin_reset.php">
                <i class="fas fa-key me-2"></i> Password Reset
            </a>
            <?php endif; ?>
            <hr style="border-color: rgba(255,255,255,0.2);">
            <a class="nav-link" href="../logout.php">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-id-card me-2"></i>Subscriptions</h2>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Member</label>
                        <select class="form-select" name="member_id">
                            <option value="">All Members</option>
                            <?php foreach($members as $member): ?>
                                <option value="<?= $member['id'] ?>" <?= $member_id==$member['id']?'selected':'' ?>>
                                    <?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="Pending" <?= $status_filter=='Pending'?'selected':'' ?>>Pending (Unpaid)</option>
                            <option value="Active" <?= $status_filter=='Active'?'selected':'' ?>>Active (Paid)</option>
                            <option value="Expired" <?= $status_filter=='Expired'?'selected':'' ?>>Expired</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Subscriptions Table -->
        <div class="card">
            <div class="card-header"><i class="fas fa-table me-2"></i>All Subscriptions (<?= count($subscriptions) ?>)</div>
            <div class="card-body table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Member ID</th>
                            <th>Member Name</th>
                            <th>Plan</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Amount Paid</th>
                            <th>Payment Method</th>
                            <th>Status</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($subscriptions)): ?>
                            <?php foreach($subscriptions as $index => $sub): ?>
                                <tr>
                                    <td><?= $index+1 ?></td>
                                    <td><?= htmlspecialchars($sub['member_id']) ?></td>
                                    <td><?= htmlspecialchars($sub['first_name'] . ' ' . $sub['last_name']) ?></td>
                                    <td><?= isset($plans[$sub['plan_id']]) ? htmlspecialchars($plans[$sub['plan_id']]) : 'N/A' ?></td>
                                    <td><?= date('M d, Y', strtotime($sub['start_date'])) ?></td>
                                    <td><?= date('M d, Y', strtotime($sub['end_date'])) ?></td>
                                    <td>
                                        <?php if($sub['status']=='Pending'): ?>
                                            ₹<?= number_format($sub['plan_price'],2) ?> <small class="text-muted">(Due)</small>
                                        <?php else: ?>
                                            ₹<?= number_format($sub['amount_paid'],2) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($sub['status']=='Pending'): ?>
                                            -
                                        <?php else: ?>
                                            <?= htmlspecialchars($sub['payment_method']) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $sub['status']=='Active'?'bg-success':($sub['status']=='Expired'?'bg-danger':'bg-warning') ?>" title="<?= $sub['status']=='Pending'?'Payment pending - Not yet activated':''; ?>">
                                            <?= $sub['status']=='Pending'?'🟡 Pending (Unpaid)':($sub['status']=='Active'?'✓ Active':($sub['status']=='Expired'?'✗ Expired':$sub['status'])) ?>
                                        </span>
                                    </td>
                                    <td><?= date('M d, Y H:i', strtotime($sub['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted">No subscriptions found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
