<?php
// attendance.php - Attendance Management
require_once '../config.php';
require_once '../session.php';
requireLogin();

$db = new Database();
$conn = $db->connect();

$success = '';
$error = '';

// Handle Check-in/Check-out
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'checkin':
                $member_id = $_POST['member_id'];
                
                // Verify member exists and is active
                $stmt = $conn->prepare("SELECT * FROM members WHERE member_id = ? AND status = 'Active'");
                $stmt->execute([$member_id]);
                $member = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($member) {
                    // Check if already checked in today
                    $stmt = $conn->prepare("SELECT * FROM attendance WHERE member_id = ? AND date = CURDATE() AND check_out_time IS NULL");
                    $stmt->execute([$member['id']]);
                    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($existing) {
                        $error = "Member is already checked in!";
                    } else {
                        // Check if subscription is active
                        $stmt = $conn->prepare("SELECT * FROM subscriptions WHERE member_id = ? AND status = 'Active' AND end_date >= CURDATE()");
                        $stmt->execute([$member['id']]);
                        $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$subscription) {
                            $error = "Member has no active subscription!";
                        } else {
                            // Proceed with check-in
                            $stmt = $conn->prepare("INSERT INTO attendance (member_id, check_in_time, date) VALUES (?, NOW(), CURDATE())");
                            $stmt->execute([$member['id']]);
                            $success = "Check-in successful for " . $member['first_name'] . " " . $member['last_name'];
                        }
                    }
                } else {
                    $error = "Member not found or inactive!";
                }
                break;
                
            case 'checkout':
                $attendance_id = $_POST['attendance_id'];
                $stmt = $conn->prepare("UPDATE attendance SET check_out_time = NOW() WHERE id = ?");
                $stmt->execute([$attendance_id]);
                $success = "Check-out successful!";
                break;
        }
    }
}

// Get today's attendance
$date_filter = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$stmt = $conn->prepare("
    SELECT a.*, m.member_id, m.first_name, m.last_name, m.photo,
    TIMEDIFF(a.check_out_time, a.check_in_time) as duration
    FROM attendance a
    JOIN members m ON a.member_id = m.id
    WHERE a.date = ?
    ORDER BY a.check_in_time DESC
");
$stmt->execute([$date_filter]);
$attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get attendance statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM attendance WHERE date = ?");
$stmt->execute([$date_filter]);
$total_attendance = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM attendance WHERE date = ? AND check_out_time IS NOT NULL");
$stmt->execute([$date_filter]);
$completed_sessions = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM attendance WHERE date = ? AND check_out_time IS NULL");
$stmt->execute([$date_filter]);
$active_sessions = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Calculate average duration
$stmt = $conn->prepare("SELECT AVG(TIME_TO_SEC(TIMEDIFF(check_out_time, check_in_time))) as avg_duration FROM attendance WHERE date = ? AND check_out_time IS NOT NULL");
$stmt->execute([$date_filter]);
$avg_duration = $stmt->fetch(PDO::FETCH_ASSOC)['avg_duration'];
$avg_duration = $avg_duration ? gmdate("H:i", $avg_duration) : '00:00';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - Gym Management System</title>
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
        
        .stat-card.total {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .stat-card.active {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .stat-card.completed {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }
        
        .stat-card.duration {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .checkin-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
        }
        
        .checkin-input {
            font-size: 2rem;
            text-align: center;
            border: 3px solid white;
            background: rgba(255,255,255,0.2);
            color: white;
        }
        
        .checkin-input::placeholder {
            color: rgba(255,255,255,0.7);
        }
        
        .checkin-input:focus {
            background: white;
            color: #333;
            border-color: white;
        }
        
        .btn-checkin {
            background: white;
            color: #667eea;
            font-weight: bold;
            border: none;
            padding: 15px;
            font-size: 1.2rem;
        }
        
        .btn-checkin:hover {
            background: #f0f0f0;
            color: #667eea;
        }
        
        .attendance-row {
            transition: all 0.3s;
        }
        
        .attendance-row:hover {
            background-color: #f8f9fa;
        }
        
        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        
        .status-active {
            background-color: #28a745;
            animation: pulse 2s infinite;
        }
        
        .status-completed {
            background-color: #6c757d;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
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
            <a class="nav-link active" href="attendance.php">
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
        <h2 class="mb-4"><i class="fas fa-clipboard-check me-2"></i>Attendance Management</h2>

        <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card total">
                    <i class="fas fa-users fa-2x mb-2"></i>
                    <h3><?php echo $total_attendance; ?></h3>
                    <p class="mb-0">Total Attendance</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card active">
                    <i class="fas fa-user-check fa-2x mb-2"></i>
                    <h3><?php echo $active_sessions; ?></h3>
                    <p class="mb-0">Currently Active</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card completed">
                    <i class="fas fa-check-double fa-2x mb-2"></i>
                    <h3><?php echo $completed_sessions; ?></h3>
                    <p class="mb-0">Completed Sessions</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card duration">
                    <i class="fas fa-clock fa-2x mb-2"></i>
                    <h3><?php echo $avg_duration; ?></h3>
                    <p class="mb-0">Average Duration</p>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Check-in Form -->
            <div class="col-md-4 mb-4">
                <div class="checkin-card">
                    <h4 class="text-center mb-4">
                        <i class="fas fa-qrcode fa-2x mb-3"></i><br>
                        Quick Check-In
                    </h4>
                    <form method="POST" id="checkinForm">
                        <input type="hidden" name="action" value="checkin">
                        <input type="text" 
                               class="form-control checkin-input mb-3" 
                               name="member_id" 
                               id="memberIdInput"
                               placeholder="SCAN OR ENTER ID" 
                               autocomplete="off"
                               autofocus
                               required>
                        <button type="submit" class="btn btn-checkin w-100">
                            <i class="fas fa-sign-in-alt me-2"></i>CHECK IN
                        </button>
                    </form>
                    <div class="text-center mt-4">
                        <small>Scan member ID or enter manually</small>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">
                        <i class="fas fa-calendar me-2"></i>View Date
                    </div>
                    <div class="card-body">
                        <form method="GET">
                            <input type="date" 
                                   class="form-control" 
                                   name="date" 
                                   value="<?php echo $date_filter; ?>"
                                   onchange="this.form.submit()">
                        </form>
                    </div>
                </div>
            </div>

            <!-- Attendance Records -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-list me-2"></i>Attendance for <?php echo date('F d, Y', strtotime($date_filter)); ?></span>
                        <button class="btn btn-sm btn-primary" onclick="window.print()">
                            <i class="fas fa-print me-1"></i>Print
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th>Check In</th>
                                        <th>Check Out</th>
                                        <th>Duration</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendance_records as $record): ?>
                                    <tr class="attendance-row">
                                        <td>
                                            <strong><?php echo $record['member_id']; ?></strong><br>
                                            <small><?php echo $record['first_name'] . ' ' . $record['last_name']; ?></small>
                                        </td>
                                        <td><?php echo date('h:i A', strtotime($record['check_in_time'])); ?></td>
                                        <td>
                                            <?php echo $record['check_out_time'] ? date('h:i A', strtotime($record['check_out_time'])) : '<span class="text-muted">-</span>'; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($record['duration']) {
                                                $duration = $record['duration'];
                                                echo $duration;
                                            } else {
                                                echo '<span class="text-muted">In Progress</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($record['check_out_time']): ?>
                                                <span class="status-indicator status-completed"></span>
                                                <span class="badge bg-secondary">Completed</span>
                                            <?php else: ?>
                                                <span class="status-indicator status-active"></span>
                                                <span class="badge bg-success">Active</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!$record['check_out_time']): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="checkout">
                                                <input type="hidden" name="attendance_id" value="<?php echo $record['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="fas fa-sign-out-alt me-1"></i>Check Out
                                                </button>
                                            </form>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($attendance_records)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            <i class="fas fa-info-circle fa-2x mb-2"></i><br>
                                            No attendance records for this date
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-focus on member ID input after form submission
        document.getElementById('checkinForm').addEventListener('submit', function() {
            setTimeout(function() {
                document.getElementById('memberIdInput').focus();
            }, 100);
        });

        // Clear input after successful check-in
        <?php if ($success): ?>
        document.getElementById('memberIdInput').value = '';
        document.getElementById('memberIdInput').focus();
        <?php endif; ?>

        // Auto-refresh every 30 seconds to show real-time updates
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>