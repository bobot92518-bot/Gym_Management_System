<?php
// member_landing.php – Member Login Page (Manual + QR)
require_once '../config.php';
session_start();

$db = new Database();
$conn = $db->connect();

$success = '';
$error = '';

// Handle login (manual or QR)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $member_id_input = trim($_POST['member_id']);

    // Find active member
    $stmt = $conn->prepare("SELECT * FROM members WHERE member_id = ? AND status = 'Active'");
    $stmt->execute([$member_id_input]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($member) {
        $member_id = $member['id'];

        // Set session
        $_SESSION['member_id'] = $member_id;
        $_SESSION['member_name'] = $member['first_name'] . ' ' . $member['last_name'];

        // Redirect to dashboard
        header("Location: member_dashboard.php");
        exit;
    } else {
        $error = "Member not found or inactive!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Member Login - Gym Management System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<style>
:root { --primary-color: #ff6b6b; --dark-color: #2c3e50; }
body { font-family:'Segoe UI', sans-serif; background:linear-gradient(135deg,var(--dark-color) 0%,#34495e 100%); min-height:100vh; display:flex; align-items:center; justify-content:center; }
.login-card { background:white; border-radius:20px; padding:40px; box-shadow:0 15px 35px rgba(0,0,0,0.1); max-width:500px; width:100%; }
.logo { text-align:center; margin-bottom:30px; }
.logo i { font-size:4rem; color:var(--primary-color); }
.login-input { font-size:1.5rem; text-align:center; border:3px solid #e9ecef; border-radius:10px; padding:15px; margin-bottom:20px; }
.login-input:focus { border-color:var(--primary-color); box-shadow:0 0 0 0.2rem rgba(255,107,107,0.25); }
.btn-login { background:linear-gradient(135deg,var(--primary-color) 0%,#ff8e8e 100%); border:none; padding:15px; font-size:1.2rem; font-weight:bold; border-radius:10px; width:100%; }
.btn-login:hover { background:linear-gradient(135deg,#ff8e8e 0%,var(--primary-color) 100%); }
.btn-mode { background:white; border:2px solid var(--primary-color); color:var(--primary-color); font-weight:600; border-radius:10px; padding:10px; width:48%; transition:0.3s; }
.btn-mode.active, .btn-mode:hover { background:var(--primary-color); color:white; }
.alert { border-radius:10px; margin-bottom:20px; }
#qrReader { width:100%; display:none; margin-top:20px; border:2px dashed #ccc; border-radius:10px; padding:10px; }
</style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="login-card">
                <div class="logo">
                    <i class="fas fa-dumbbell"></i>
                    <h2>Gym Login</h2>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?= $success ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><?= $error ?></div>
                <?php endif; ?>

                <div class="d-flex justify-content-between mb-3">
                    <button type="button" id="manualBtn" class="btn btn-mode active"><i class="fas fa-keyboard me-2"></i>Manual</button>
                    <button type="button" id="scanBtn" class="btn btn-mode"><i class="fas fa-qrcode me-2"></i>Scan QR</button>
                </div>

                <form method="POST" id="manualForm">
                    <input type="text" class="form-control login-input" name="member_id" placeholder="Enter Member ID" required autofocus>
                    <button type="submit" name="login" class="btn btn-login mt-2"><i class="fas fa-sign-in-alt me-2"></i>LOGIN</button>
                </form>

                <div id="qrReader"></div>
                <div class="text-center mt-4"><small class="text-muted">Scan QR or enter ID manually</small></div>
            </div>
        </div>
    </div>
</div>

<script>
const manualBtn = document.getElementById('manualBtn');
const scanBtn = document.getElementById('scanBtn');
const manualForm = document.getElementById('manualForm');
const qrReader = document.getElementById('qrReader');
let html5QrCode;

manualBtn.addEventListener('click', () => {
    manualForm.style.display = 'block';
    qrReader.style.display = 'none';
    manualBtn.classList.add('active');
    scanBtn.classList.remove('active');
    if (html5QrCode) html5QrCode.stop();
});

scanBtn.addEventListener('click', () => {
    manualForm.style.display = 'none';
    qrReader.style.display = 'block';
    manualBtn.classList.remove('active');
    scanBtn.classList.add('active');

    html5QrCode = new Html5Qrcode("qrReader");
    html5QrCode.start(
        { facingMode: "environment" },
        { fps: 10, qrbox: 250 },
        (decodedText) => {
            fetch('member_landing.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `login=1&member_id=${encodeURIComponent(decodedText)}`
            }).then(res => res.text()).then(html => {
                document.open();
                document.write(html);
                document.close();
            });
        }
    ).catch(err => console.error("Camera error:", err));
});
</script>
</body>
</html>
