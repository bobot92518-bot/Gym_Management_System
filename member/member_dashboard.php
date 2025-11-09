<?php
// member_dashboard.php - Member Dashboard
require_once '../config.php';
require_once '../session.php';

// For members, they access via check-in or direct link, but for now assume session
// Note: This assumes members are logged in via some mechanism, perhaps after check-in

$db = new Database();
$conn = $db->connect();

// Assume member ID is in session, e.g., $_SESSION['member_id']
// For demo, hardcode or get from GET, but in real app, set during check-in
$member_id = isset($_SESSION['member_id']) ? $_SESSION['member_id'] : (isset($_GET['member_id']) ? $_GET['member_id'] : null);

if (!$member_id) {
    header('Location: login.php');
    exit;
}

// Get member details
$stmt = $conn->prepare("SELECT * FROM members WHERE id = ?");
$stmt->execute([$member_id]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    die("Member not found");
}

// Get membership details
$stmt = $conn->prepare("SELECT s.*, p.plan_name, p.price FROM subscriptions s JOIN membership_plans p ON s.plan_id = p.id WHERE s.member_id = ? AND s.status = 'Active' ORDER BY s.end_date DESC LIMIT 1");
$stmt->execute([$member_id]);
$subscription = $stmt->fetch(PDO::FETCH_ASSOC);

// Get payment history
$stmt = $conn->prepare("SELECT * FROM payments WHERE member_id = ? ORDER BY payment_date DESC LIMIT 10");
$stmt->execute([$member_id]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get attendance records
$stmt = $conn->prepare("SELECT a.*, TIMEDIFF(a.check_out_time, a.check_in_time) as duration FROM attendance a WHERE a.member_id = ? ORDER BY a.date DESC LIMIT 20");
$stmt->execute([$member_id]);
$attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - Gym Management System</title>
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

        .stat-card {
            border-radius: 15px;
            padding: 20px;
            color: white;
            text-align: center;
        }

        .stat-card.membership {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-card.payments {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .stat-card.attendance {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
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
            <a class="nav-link active" href="member_dashboard.php">
                <i class="fas fa-home me-2"></i> My Dashboard
            </a>
            <a class="nav-link" href="logout.php">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <h2 class="mb-4"><i class="fas fa-user-circle me-2"></i>My Dashboard</h2>

        <!-- Welcome Section -->
        <div class="card mb-4">
            <div class="card-body">
                <h4>Welcome, <?php echo $member['first_name'] . ' ' . $member['last_name']; ?>!</h4>
                <p class="text-muted">Member ID: <?php echo $member['member_id']; ?></p>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-card membership">
                    <i class="fas fa-id-card fa-2x mb-2"></i>
                    <h5>Membership</h5>
                    <p><?php echo $subscription ? $subscription['plan_name'] : 'No Active Membership'; ?></p>
                    <small><?php echo $subscription ? 'Expires: ' . date('M d, Y', strtotime($subscription['end_date'])) : ''; ?></small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card payments">
                    <i class="fas fa-dollar-sign fa-2x mb-2"></i>
                    <h5>Recent Payment</h5>
                    <p><?php echo $payments ? '₱' . $payments[0]['amount'] : 'No Payments'; ?></p>
                    <small><?php echo $payments ? date('M d, Y', strtotime($payments[0]['payment_date'])) : ''; ?></small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card attendance">
                    <i class="fas fa-clipboard-check fa-2x mb-2"></i>
                    <h5>Last Visit</h5>
                    <p><?php echo $attendance ? date('M d, Y', strtotime($attendance[0]['date'])) : 'No Visits'; ?></p>
                    <small><?php echo $attendance && $attendance[0]['duration'] ? 'Duration: ' . $attendance[0]['duration'] : ''; ?></small>
                </div>
            </div>
        </div>

        <!-- Membership Details -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-id-card me-2"></i>Membership Details
            </div>
            <div class="card-body">
                <?php if ($subscription): ?>
                    <p><strong>Plan:</strong> <?php echo $subscription['plan_name']; ?></p>
                    <p><strong>Start Date:</strong> <?php echo date('M d, Y', strtotime($subscription['start_date'])); ?></p>
                    <p><strong>End Date:</strong> <?php echo date('M d, Y', strtotime($subscription['end_date'])); ?></p>
                    <p><strong>Status:</strong> <span class="badge bg-success"><?php echo $subscription['status']; ?></span></p>
                <?php else: ?>
                    <p>No active membership found.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Payment History -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-history me-2"></i>Payment History
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                <td>₱<?php echo $payment['amount']; ?></td>
                                <td><?php echo $payment['payment_method']; ?></td>
                                <td><span class="badge bg-success"><?php echo isset($payment['status']) ? $payment['status'] : 'Completed'; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($payments)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted">No payment history</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Attendance Records -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-clipboard-check me-2"></i>Attendance Records
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Check In</th>
                                <th>Check Out</th>
                                <th>Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance as $record): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($record['date'])); ?></td>
                                <td><?php echo date('h:i A', strtotime($record['check_in_time'])); ?></td>
                                <td><?php echo $record['check_out_time'] ? date('h:i A', strtotime($record['check_out_time'])) : '-'; ?></td>
                                <td><?php echo $record['duration'] ? $record['duration'] : 'In Progress'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($attendance)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted">No attendance records</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
