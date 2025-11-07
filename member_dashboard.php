<?php
// index.php - Landing Page / Member Dashboard
require_once 'config.php';
require_once 'session.php';

// Check if user is logged in
$is_logged_in = isLoggedIn();
$member = null;
$error = '';

if ($is_logged_in) {
    // Get member data based on logged-in user
    $db = new Database();
    $conn = $db->connect();

    // For now, we'll assume members log in with their member_id as username
    // In a real system, you'd have a separate member login or link users to members
    $member_id = $_SESSION['username']; // Assuming username is member_id

    // Get member information
    $stmt = $conn->prepare("SELECT * FROM members WHERE member_id = ?");
    $stmt->execute([$member_id]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$member) {
        // If member not found, show error
        $error = "Member profile not found. Please contact administrator.";
    } else {
    // Get subscription information
    $stmt = $conn->prepare("
        SELECT s.*, p.plan_name, p.price, p.duration_days
        FROM subscriptions s
        JOIN membership_plans p ON s.plan_id = p.id
        WHERE s.member_id = ? AND s.status = 'Active'
        ORDER BY s.end_date DESC
        LIMIT 1
    ");
    $stmt->execute([$member['id']]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get attendance statistics
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM attendance WHERE member_id = ?");
    $stmt->execute([$member['id']]);
    $total_visits = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Monthly visits (last 30 days)
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM attendance WHERE member_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $stmt->execute([$member['id']]);
    $monthly_visits = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Weekly visits (last 7 days)
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM attendance WHERE member_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
    $stmt->execute([$member['id']]);
    $weekly_visits = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Recent attendance (last 10 visits)
    $stmt = $conn->prepare("
        SELECT a.*, TIMEDIFF(a.check_out_time, a.check_in_time) as duration
        FROM attendance a
        WHERE a.member_id = ?
        ORDER BY a.date DESC, a.check_in_time DESC
        LIMIT 10
    ");
    $stmt->execute([$member['id']]);
    $recent_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate average session duration
    $stmt = $conn->prepare("
        SELECT AVG(TIME_TO_SEC(TIMEDIFF(check_out_time, check_in_time))) as avg_duration
        FROM attendance
        WHERE member_id = ? AND check_out_time IS NOT NULL
    ");
    $stmt->execute([$member['id']]);
    $avg_duration = $stmt->fetch(PDO::FETCH_ASSOC)['avg_duration'];
    $avg_duration_formatted = $avg_duration ? gmdate("H:i", $avg_duration) : '00:00';
}
}
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
            margin-left: 0;
            padding: 20px;
        }

        .main-content.with-sidebar {
            margin-left: 250px;
        }

        .welcome-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
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

        .stat-card.visits {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-card.monthly {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .stat-card.weekly {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .stat-card.duration {
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

        .qr-code {
            background: white;
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            max-width: 200px;
            margin: 0 auto;
        }

        .qr-code canvas {
            max-width: 100%;
            height: auto;
        }

        .subscription-status {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .subscription-active {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .subscription-expiring {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 1px solid #ffeaa7;
            color: #856404;
        }

        .subscription-expired {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .progress-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin: 0 auto 10px;
        }

        .progress-circle.visits {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .progress-circle.monthly {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
    </style>
</head>
<body>
    <?php if ($is_logged_in && !$error): ?>
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
            <a class="nav-link" href="member_profile.php">
                <i class="fas fa-user me-2"></i> My Profile
            </a>
            <a class="nav-link" href="member_attendance.php">
                <i class="fas fa-clipboard-check me-2"></i> My Attendance
            </a>
            <a class="nav-link" href="member_subscription.php">
                <i class="fas fa-id-card me-2"></i> My Subscription
            </a>
            <hr style="border-color: rgba(255,255,255,0.2);">
            <a class="nav-link" href="logout.php">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </a>
        </nav>
    </div>
    <?php endif; ?>

    <!-- Main Content -->
    <div class="main-content <?php echo ($is_logged_in && !$error) ? 'with-sidebar' : ''; ?>">
        <?php if (!$is_logged_in): ?>
        <!-- Landing Page for Non-Logged In Users -->
        <div class="text-center py-5">
            <div class="welcome-card mx-auto" style="max-width: 800px;">
                <h1 class="display-4 mb-4">Welcome to GYM Management System</h1>
                <p class="lead mb-4">Track your fitness journey, manage memberships, and stay motivated!</p>
                <button class="btn btn-light btn-lg" data-bs-toggle="modal" data-bs-target="#loginModal">
                    <i class="fas fa-sign-in-alt me-2"></i>Login to Your Account
                </button>
            </div>

            <div class="row mt-5">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-qrcode fa-3x text-primary mb-3"></i>
                            <h5>QR Code Check-in</h5>
                            <p>Quick and easy check-in with your personal QR code</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-chart-line fa-3x text-success mb-3"></i>
                            <h5>Progress Tracking</h5>
                            <p>Monitor your attendance and fitness progress</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-id-card fa-3x text-info mb-3"></i>
                            <h5>Membership Management</h5>
                            <p>Easy subscription tracking and renewal</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Login Modal -->
        <div class="modal fade" id="loginModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Login to Your Account</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form action="login.php" method="POST">
                            <input type="hidden" name="login" value="1">
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" name="login_username" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-control" name="login_password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Login</button>
                        </form>
                        <div class="text-center mt-3">
                            <small class="text-muted">Demo: username: admin | password: admin123</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php elseif (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
        </div>
        <?php else: ?>

        <!-- Welcome Section -->
        <div class="welcome-card">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2>Welcome back, <?php echo $member['first_name'] . ' ' . $member['last_name']; ?>!</h2>
                    <p class="mb-0">Member ID: <strong><?php echo $member['member_id']; ?></strong></p>
                    <p class="mb-0">Keep up the great work on your fitness journey!</p>
                </div>
                <div class="col-md-4 text-center">
                    <div class="qr-code">
                        <h6>Scan for Check-in</h6>
                        <div id="qrcode"></div>
                        <small class="text-muted mt-2 d-block"><?php echo $member['member_id']; ?></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card visits">
                    <div class="text-center">
                        <i class="fas fa-calendar-check fa-3x mb-3" style="opacity: 0.3;"></i>
                        <h2 class="mb-2"><?php echo $total_visits; ?></h2>
                        <p class="mb-0">Total Visits</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card monthly">
                    <div class="text-center">
                        <i class="fas fa-calendar-alt fa-3x mb-3" style="opacity: 0.3;"></i>
                        <h2 class="mb-2"><?php echo $monthly_visits; ?></h2>
                        <p class="mb-0">This Month</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card weekly">
                    <div class="text-center">
                        <i class="fas fa-calendar-week fa-3x mb-3" style="opacity: 0.3;"></i>
                        <h2 class="mb-2"><?php echo $weekly_visits; ?></h2>
                        <p class="mb-0">This Week</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card duration">
                    <div class="text-center">
                        <i class="fas fa-clock fa-3x mb-3" style="opacity: 0.3;"></i>
                        <h2 class="mb-2"><?php echo $avg_duration_formatted; ?></h2>
                        <p class="mb-0">Avg Session</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Subscription Status -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-id-card me-2"></i>Subscription Status
                    </div>
                    <div class="card-body">
                        <?php if ($subscription): ?>
                            <?php
                            $end_date = strtotime($subscription['end_date']);
                            $today = strtotime(date('Y-m-d'));
                            $days_left = ($end_date - $today) / 86400;

                            $status_class = '';
                            $status_text = '';
                            if ($days_left > 7) {
                                $status_class = 'subscription-active';
                                $status_text = 'Active';
                            } elseif ($days_left > 0) {
                                $status_class = 'subscription-expiring';
                                $status_text = 'Expiring Soon';
                            } else {
                                $status_class = 'subscription-expired';
                                $status_text = 'Expired';
                            }
                            ?>
                            <div class="subscription-status <?php echo $status_class; ?>">
                                <h5><?php echo $subscription['plan_name']; ?></h5>
                                <p class="mb-1"><strong>Status:</strong> <?php echo $status_text; ?></p>
                                <p class="mb-1"><strong>Expires:</strong> <?php echo date('F d, Y', $end_date); ?></p>
                                <?php if ($days_left > 0): ?>
                                    <p class="mb-0"><strong>Days Left:</strong> <?php echo ceil($days_left); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="subscription-status subscription-expired">
                                <h5>No Active Subscription</h5>
                                <p class="mb-0">Please contact the gym administration to renew your membership.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-history me-2"></i>Recent Activity
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_attendance)): ?>
                            <div class="list-group">
                                <?php foreach ($recent_attendance as $att): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?php echo date('M d, Y', strtotime($att['date'])); ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                Check-in: <?php echo date('h:i A', strtotime($att['check_in_time'])); ?>
                                                <?php if ($att['check_out_time']): ?>
                                                    | Check-out: <?php echo date('h:i A', strtotime($att['check_out_time'])); ?>
                                                    | Duration: <?php echo $att['duration']; ?>
                                                <?php else: ?>
                                                    | <span class="text-warning">In Progress</span>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <span class="badge bg-<?php echo $att['check_out_time'] ? 'success' : 'warning'; ?>">
                                            <?php echo $att['check_out_time'] ? 'Completed' : 'Active'; ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">No attendance records yet</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Progress Section -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-line me-2"></i>Your Progress
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="progress-circle visits">
                                    <?php echo $total_visits; ?>
                                </div>
                                <h6>Total Visits</h6>
                            </div>
                            <div class="col-md-3">
                                <div class="progress-circle monthly">
                                    <?php echo $monthly_visits; ?>
                                </div>
                                <h6>This Month</h6>
                            </div>
                            <div class="col-md-3">
                                <div class="progress-circle weekly">
                                    <?php echo $weekly_visits; ?>
                                </div>
                                <h6>This Week</h6>
                            </div>
                            <div class="col-md-3">
                                <div class="progress-circle duration">
                                    <?php echo $avg_duration_formatted; ?>
                                </div>
                                <h6>Avg Session</h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <script>
        // Generate QR Code
        document.addEventListener('DOMContentLoaded', function() {
            const memberId = '<?php echo $member['member_id']; ?>';
            QRCode.toCanvas(document.getElementById('qrcode'), memberId, {
                width: 150,
                height: 150,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
        });
    </script>
</body>
</html>
