<?php
require_once('../config.php');
session_start();

// Redirect if not logged in
if (!isset($_SESSION['member_id'])) {
    header("Location: member_landing.php");
    exit;
}

$db = new Database();
$conn = $db->connect();

$member_id = $_SESSION['member_id'];
$member_name = $_SESSION['member_name'] ?? '';

// Get member info
$stmt = $conn->prepare("SELECT * FROM members WHERE id = ?");
$stmt->execute([$member_id]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

// Get active subscription (only active and current date in range)
$stmt = $conn->prepare("
    SELECT * 
    FROM subscriptions 
    WHERE member_id = ? AND status = 'Active' AND start_date <= CURDATE() AND end_date >= CURDATE()
    ORDER BY end_date DESC
    LIMIT 1
");
$stmt->execute([$member_id]);
$subscription = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent payments (last 10)
$stmt = $conn->prepare("
    SELECT * 
    FROM subscriptions 
    WHERE member_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$member_id]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get attendance (last 20)
$stmt = $conn->prepare("
    SELECT a.*, TIMEDIFF(a.check_out_time, a.check_in_time) AS duration 
    FROM attendance a 
    WHERE a.member_id = ? 
    ORDER BY a.date DESC 
    LIMIT 20
");
$stmt->execute([$member_id]);
$attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Member Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
body{font-family:'Segoe UI', sans-serif;background:#f5f5f5;margin:0;padding:0;}
.sidebar{position:fixed;top:0;left:0;width:250px;height:100%;background:#34495e;color:white;padding-top:20px;}
.sidebar .logo{text-align:center;padding:20px;border-bottom:1px solid rgba(255,255,255,0.1);}
.sidebar .logo i{font-size:3rem;color:#ff6b6b;}
.sidebar .nav-link{display:block;padding:15px 25px;color:white;text-decoration:none;}
.sidebar .nav-link:hover, .sidebar .nav-link.active{background: rgba(255,255,255,0.1);}
.main-content{margin-left:250px;padding:20px;}
.card{border-radius:15px;box-shadow:0 5px 15px rgba(0,0,0,0.08);}
.stat-card{border-radius:15px;padding:20px;color:white;text-align:center;}
.stat-card.membership{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);}
.stat-card.payments{background:linear-gradient(135deg,#f093fb 0%,#f5576c 100%);}
.stat-card.attendance{background:linear-gradient(135deg,#4facfe 0%,#00f2fe 100%);}
.table th, .table td{vertical-align:middle;}
</style>
</head>
<body>

<div class="sidebar">
    <div class="logo">
        <i class="fas fa-dumbbell"></i>
        <h4>GYM Manager</h4>
    </div>
    <a class="nav-link active" href="member_dashboard.php"><i class="fas fa-home me-2"></i>Dashboard</a>
    <a class="nav-link" href="view_profile.php"><i class="fas fa-user me-2"></i>Edit Profile</a>
    <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
</div>


<div class="main-content">
<h2>Welcome, <?php echo $member['first_name'].' '.$member['last_name']; ?></h2>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="stat-card membership">
            <i class="fas fa-id-card fa-2x mb-2"></i>
            <h5>Membership</h5>
            <p><?php echo $subscription ? $subscription['status'] : 'No Active Membership'; ?></p>
            <?php if($subscription): ?>
                <small>Plan ID: <?php echo $subscription['plan_id']; ?><br>Ends: <?php echo date('M d, Y', strtotime($subscription['end_date'])); ?></small>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card payments">
            <i class="fas fa-dollar-sign fa-2x mb-2"></i>
            <h5>Recent Payment</h5>
            <p><?php echo $payments ? '₱'.$payments[0]['amount_paid'] : 'No Payments'; ?></p>
            <small><?php echo $payments ? date('M d, Y', strtotime($payments[0]['created_at'])) : ''; ?></small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card attendance">
            <i class="fas fa-clipboard-check fa-2x mb-2"></i>
            <h5>Last Visit</h5>
            <p><?php echo $attendance ? date('M d, Y', strtotime($attendance[0]['date'])) : 'No Visits'; ?></p>
            <small><?php echo $attendance && $attendance[0]['duration'] ? 'Duration: '.$attendance[0]['duration'] : ''; ?></small>
        </div>
    </div>
</div>

<!-- Membership Details -->
<div class="card mb-4">
    <div class="card-header"><i class="fas fa-id-card me-2"></i>Membership Details</div>
    <div class="card-body">
        <?php if($subscription): ?>
            <p><strong>Plan ID:</strong> <?php echo $subscription['plan_id']; ?></p>
            <p><strong>Status:</strong> <?php echo $subscription['status']; ?></p>
            <p><strong>Start Date:</strong> <?php echo date('M d, Y', strtotime($subscription['start_date'])); ?></p>
            <p><strong>End Date:</strong> <?php echo date('M d, Y', strtotime($subscription['end_date'])); ?></p>
            <p><strong>Amount Paid:</strong> ₱<?php echo $subscription['amount_paid']; ?></p>
            <p><strong>Payment Method:</strong> <?php echo $subscription['payment_method']; ?></p>
        <?php else: ?>
            <p>No active membership.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Payment History -->
<div class="card mb-4">
    <div class="card-header"><i class="fas fa-history me-2"></i>Payment History</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Plan ID</th>
                        <th>Amount Paid</th>
                        <th>Status</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Payment Method</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($payments): ?>
                        <?php foreach($payments as $pay): ?>
                        <tr>
                            <td><?php echo $pay['plan_id']; ?></td>
                            <td>₱<?php echo $pay['amount_paid']; ?></td>
                            <td><?php echo $pay['status']; ?></td>
                            <td><?php echo date('M d, Y', strtotime($pay['start_date'])); ?></td>
                            <td><?php echo date('M d, Y', strtotime($pay['end_date'])); ?></td>
                            <td><?php echo $pay['payment_method']; ?></td>
                            <td><?php echo date('M d, Y', strtotime($pay['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center text-muted">No payment history</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Attendance Records -->
<div class="card mb-4">
    <div class="card-header"><i class="fas fa-clipboard-check me-2"></i>Attendance Records</div>
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
                    <?php if($attendance): ?>
                        <?php foreach($attendance as $a): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($a['date'])); ?></td>
                            <td><?php echo date('h:i A', strtotime($a['check_in_time'])); ?></td>
                            <td><?php echo $a['check_out_time'] ? date('h:i A', strtotime($a['check_out_time'])) : '-'; ?></td>
                            <td><?php echo $a['duration'] ? $a['duration'] : 'In Progress'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center text-muted">No attendance records</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div>
</body>
</html>
