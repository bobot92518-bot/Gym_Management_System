<?php
// edit_profile.php – Member Profile Editing Page
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
$success = '';
$error = '';

// Fetch current member data
$stmt = $conn->prepare("SELECT * FROM members WHERE id = ?");
$stmt->execute([$member_id]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    die("Member not found.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $dob = trim($_POST['date_of_birth']);
    $gender = trim($_POST['gender']);
    $emergency_contact = trim($_POST['emergency_contact']);
    $emergency_phone = trim($_POST['emergency_phone']);

    // Profile picture handling
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
        $allowed_ext = ['jpg','jpeg','png','gif'];
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed_ext)) {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $photo_path = $upload_dir . 'profile_' . $member_id . '.' . $ext;
            move_uploaded_file($_FILES['photo']['tmp_name'], $photo_path);
        } else {
            $error .= " Invalid profile picture type.";
        }
    }

    if (!$error) {
        $sql = "UPDATE members SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?, date_of_birth = ?, gender = ?, emergency_contact = ?, emergency_phone = ?";
        $params = [$first_name, $last_name, $email, $phone, $address, $dob, $gender, $emergency_contact, $emergency_phone];

        if (isset($photo_path)) {
            $sql .= ", photo = ?";
            $params[] = $photo_path;
        }

        $sql .= " WHERE id = ?";
        $params[] = $member_id;

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        $success = "Profile updated successfully!";

        // Refresh member data
        $stmt = $conn->prepare("SELECT * FROM members WHERE id = ?");
        $stmt->execute([$member_id]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Profile - Member</title>
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
.profile-pic{width:120px;height:120px;border-radius:50%;object-fit:cover;border:2px solid #ddd;}
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
    <div class="profile-card">
        <h3 class="text-center mb-4"><i class="fas fa-user-edit me-2"></i>Edit Profile</h3>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="text-center mb-3">
                <img src="<?= isset($member['photo']) && file_exists($member['photo']) ? $member['photo'] : 'https://via.placeholder.com/120' ?>" class="profile-pic mb-2" id="profilePreview">
                <input type="file" name="photo" accept="image/*" class="form-control mt-2" onchange="previewProfile(event)">
            </div>

            <div class="mb-3">
                <label>First Name</label>
                <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($member['first_name']) ?>" required>
            </div>

            <div class="mb-3">
                <label>Last Name</label>
                <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($member['last_name']) ?>" required>
            </div>

            <div class="mb-3">
                <label>Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($member['email']) ?>">
            </div>

            <div class="mb-3">
                <label>Phone</label>
                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($member['phone']) ?>">
            </div>

            <div class="mb-3">
                <label>Address</label>
                <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($member['address']) ?>">
            </div>

            <div class="mb-3">
                <label>Date of Birth</label>
                <input type="date" name="date_of_birth" class="form-control" value="<?= htmlspecialchars($member['date_of_birth']) ?>">
            </div>

            <div class="mb-3">
                <label>Gender</label>
                <select name="gender" class="form-control">
                    <option value="Male" <?= $member['gender']=='Male'?'selected':'' ?>>Male</option>
                    <option value="Female" <?= $member['gender']=='Female'?'selected':'' ?>>Female</option>
                </select>
            </div>

            <div class="mb-3">
                <label>Emergency Contact</label>
                <input type="text" name="emergency_contact" class="form-control" value="<?= htmlspecialchars($member['emergency_contact']) ?>">
            </div>

            <div class="mb-3">
                <label>Emergency Phone</label>
                <input type="text" name="emergency_phone" class="form-control" value="<?= htmlspecialchars($member['emergency_phone']) ?>">
            </div>

            <button type="submit" name="update_profile" class="btn btn-primary w-100"><i class="fas fa-save me-2"></i>Update Profile</button>
        </form>
    </div>
</div>

<script>
function previewProfile(event) {
    const reader = new FileReader();
    reader.onload = function() {
        document.getElementById('profilePreview').src = reader.result;
    };
    reader.readAsDataURL(event.target.files[0]);
}
</script>

</body>
</html>
