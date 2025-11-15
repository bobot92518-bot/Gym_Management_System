<?php
// view_profile.php – Member Profile Viewing Page
require_once '../config.php';
session_start();

// Ensure member is logged in
if (!isset($_SESSION['member_id'])) {
    header("Location: member_landing.php");
    exit;
}

$db = new Database();
$conn = $db->connect();
$member_id = $_SESSION['member_id'];
$member_name = $_SESSION['member_name'] ?? '';

// Fetch member data
$stmt = $conn->prepare("SELECT * FROM members WHERE id = ?");
$stmt->execute([$member_id]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    die("Member not found.");
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Profile - Member</title>
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
.profile-card{max-width:700px;margin:auto;background:white;padding:30px;border-radius:15px;box-shadow:0 10px 25px rgba(0,0,0,0.1);}
.profile-pic{width:150px;height:150px;border-radius:50%;object-fit:cover;border:2px solid #ddd;}
.label{font-weight:600;}
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
    <div class="profile-card text-center">
        <img src="<?= isset($member['photo']) && file_exists($member['photo']) ? $member['photo'] : 'https://via.placeholder.com/150' ?>" class="profile-pic mb-3">
        <h3><?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?></h3>
        <p class="text-muted">Member ID: <?= htmlspecialchars($member['member_id']) ?></p>

        <div class="row mt-4 text-start">
            <div class="col-md-6 mb-3">
                <p class="label">Email:</p>
                <p><?= htmlspecialchars($member['email']) ?></p>
            </div>
            <div class="col-md-6 mb-3">
                <p class="label">Phone:</p>
                <p><?= htmlspecialchars($member['phone']) ?></p>
            </div>
            <div class="col-md-6 mb-3">
                <p class="label">Address:</p>
                <p><?= htmlspecialchars($member['address']) ?></p>
            </div>
            <div class="col-md-6 mb-3">
                <p class="label">Date of Birth:</p>
                <p><?= htmlspecialchars($member['date_of_birth']) ?></p>
            </div>
            <div class="col-md-6 mb-3">
                <p class="label">Gender:</p>
                <p><?= htmlspecialchars($member['gender']) ?></p>
            </div>
            <div class="col-md-6 mb-3">
                <p class="label">Emergency Contact:</p>
                <p><?= htmlspecialchars($member['emergency_contact']) ?></p>
            </div>
            <div class="col-md-6 mb-3">
                <p class="label">Emergency Phone:</p>
                <p><?= htmlspecialchars($member['emergency_phone']) ?></p>
            </div>
            <div class="col-md-6 mb-3">
                <p class="label">Status:</p>
                <p><?= htmlspecialchars($member['status']) ?></p>
            </div>
        </div>

        <div class="mt-4">
            <a href="edit_profile.php" class="btn btn-primary"><i class="fas fa-user-edit me-2"></i>Edit Profile</a>
            <a href="member_dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Back to Dashboard</a>
        </div>
    </div>
</div>

</body>
</html>
