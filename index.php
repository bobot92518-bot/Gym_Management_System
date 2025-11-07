<?php
// index.php - Landing/Dashboard Page
require_once 'config.php';
require_once 'session.php';
requireLogin();

$db = new Database();
$conn = $db->connect();

// Get dashboard statistics
$stats = [];

// Total members
$stmt = $conn->query("SELECT COUNT(*) as total FROM members WHERE status = 'Active'");
$stats['total_members'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Active subscriptions
$stmt = $conn->query("SELECT COUNT(*) as total FROM subscriptions WHERE status = 'Active' AND end_date >= CURDATE()");
$stats['active_subscriptions'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Today's attendance
$stmt = $conn->query("SELECT COUNT(*) as total FROM attendance WHERE date = CURDATE()");
$stats['today_attendance'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Monthly revenue
$stmt = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())");
$stats['monthly_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Recent attendance
$stmt = $conn->prepare("
    SELECT a.*, m.first_name, m.last_name, m.member_id 
    FROM attendance a 
    JOIN members m ON a.member_id = m.id 
    WHERE a.date = CURDATE() 
    ORDER BY a.check_in_time DESC 
    LIMIT 10
");
$stmt->execute();
$recent_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Expiring subscriptions (next 7 days)
$stmt = $conn->prepare("
    SELECT s.*, m.first_name, m.last_name, m.member_id, m.phone, p.plan_name
    FROM subscriptions s
    JOIN members m ON s.member_id = m.id
    JOIN membership_plans p ON s.plan_id = p.id
    WHERE s.status = 'Active' 
    AND s.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY s.end_date ASC
");
$stmt->execute();
$expiring_subs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gym Management System - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #ff6b6b;
            --secondary-color: #4ecdc4;
            --dark-color: #2c3e50;
            --light-color: #f8f9fa;
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
            z-index: 1000;
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
        
        .stat-card {
            border-radius: 15px;
            padding: 25px;
            color: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.members {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .stat-card.subscriptions {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .stat-card.attendance {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .stat-card.revenue {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
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
        
        .table {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .badge-success {
            background: #28a745;
        }
        
        .badge-warning {
            background: #ffc107;
        }
        
        .badge-danger {
            background: #dc3545;
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
            <a class="nav-link active" href="index.php">
                <i class="fas fa-home me-2"></i> Dashboard
            </a>
            <a class="nav-link" href="members.php">
                <i class="fas fa-users me-2"></i> Members
            </a>
            <a class="nav-link" href="attendance.php">
                <i class="fas fa-clipboard-check me-2"></i> Attendance
            </a>
            <a class="nav-link" href="subscriptions.php">
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
            <a class="nav-link" href="member_dashboard.php">
                <i class="fas fa-user-circle me-2"></i> Member Dashboard
            </a>
            <?php if (isAdmin()): ?>
            <a class="nav-link" href="admin_reset.php">
                <i class="fas fa-key me-2"></i> Password Reset
            </a>
            <?php endif; ?>
            <hr style="border-color: rgba(255,255,255,0.2);">
            <a class="nav-link" href="logout.php">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Dashboard</h2>
            <div>
                <span class="me-3">Welcome, <strong><?php echo $_SESSION['full_name']; ?></strong></span>
                <span class="badge bg-primary"><?php echo strtoupper($_SESSION['role']); ?></span>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card members">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-2" style="opacity: 0.8;">Total Members</h6>
                            <h2 class="mb-0"><?php echo $stats['total_members']; ?></h2>
                        </div>
                        <i class="fas fa-users fa-3x" style="opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card subscriptions">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-2" style="opacity: 0.8;">Active Subscriptions</h6>
                            <h2 class="mb-0"><?php echo $stats['active_subscriptions']; ?></h2>
                        </div>
                        <i class="fas fa-id-card fa-3x" style="opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card attendance">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-2" style="opacity: 0.8;">Today's Attendance</h6>
                            <h2 class="mb-0"><?php echo $stats['today_attendance']; ?></h2>
                        </div>
                        <i class="fas fa-clipboard-check fa-3x" style="opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card revenue">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-2" style="opacity: 0.8;">Monthly Revenue</h6>
                            <h2 class="mb-0">₱<?php echo number_format($stats['monthly_revenue'], 2); ?></h2>
                        </div>
                        <i class="fas fa-dollar-sign fa-3x" style="opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity and Alerts -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-clock me-2"></i>Today's Attendance
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Member ID</th>
                                        <th>Name</th>
                                        <th>Check In</th>
                                        <th>Check Out</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_attendance as $att): ?>
                                    <tr>
                                        <td><?php echo $att['member_id']; ?></td>
                                        <td><?php echo $att['first_name'] . ' ' . $att['last_name']; ?></td>
                                        <td><?php echo date('h:i A', strtotime($att['check_in_time'])); ?></td>
                                        <td><?php echo $att['check_out_time'] ? date('h:i A', strtotime($att['check_out_time'])) : '-'; ?></td>
                                        <td>
                                            <?php if ($att['check_out_time']): ?>
                                                <span class="badge badge-success">Completed</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">In Progress</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($recent_attendance)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No attendance recorded today</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-exclamation-triangle me-2"></i>Expiring Soon
                    </div>
                    <div class="card-body">
                        <?php if (empty($expiring_subs)): ?>
                            <p class="text-muted text-center">No subscriptions expiring in the next 7 days</p>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($expiring_subs as $sub): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?php echo $sub['first_name'] . ' ' . $sub['last_name']; ?></h6>
                                            <small class="text-muted"><?php echo $sub['member_id']; ?></small>
                                            <p class="mb-1"><small><?php echo $sub['plan_name']; ?></small></p>
                                        </div>
                                        <span class="badge badge-danger"><?php echo date('M d', strtotime($sub['end_date'])); ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>