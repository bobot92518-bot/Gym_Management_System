<?php
// member_landing.php - Member Landing Page with Check-in
require_once '../config.php';

$db = new Database();
$conn = $db->connect();

$success = '';
$error = '';

// Handle Check-in
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkin'])) {
    $member_id = $_POST['member_id'];

    // Verify member exists and is active
    $stmt = $conn->prepare("SELECT * FROM members WHERE member_id = ? AND status = 'Active'");
    $stmt->execute([$member_id]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($member) {
        // Check if subscription is active
        $stmt = $conn->prepare("SELECT * FROM subscriptions WHERE member_id = ? AND status = 'Active' AND end_date >= CURDATE()");
        $stmt->execute([$member['id']]);
        $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$subscription) {
            $error = "No active subscription found!";
        } else {
            // Set session for member
            session_start();
            $_SESSION['member_id'] = $member['id'];
            $_SESSION['member_name'] = $member['first_name'] . ' ' . $member['last_name'];

            $success = "Check-in successful! Welcome, " . $member['first_name'] . " " . $member['last_name'];

            // Redirect to dashboard after 2 seconds
            header("refresh:2;url=member_dashboard.php");
        }
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
    <title>Member Check-In - Gym Management System</title>
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
            background: linear-gradient(135deg, var(--dark-color) 0%, #34495e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .checkin-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 100%;
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo i {
            font-size: 4rem;
            color: var(--primary-color);
        }

        .checkin-input {
            font-size: 1.5rem;
            text-align: center;
            border: 3px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .checkin-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(255, 107, 107, 0.25);
        }

        .btn-checkin {
            background: linear-gradient(135deg, var(--primary-color) 0%, #ff8e8e 100%);
            border: none;
            padding: 15px;
            font-size: 1.2rem;
            font-weight: bold;
            border-radius: 10px;
            width: 100%;
        }

        .btn-checkin:hover {
            background: linear-gradient(135deg, #ff8e8e 0%, var(--primary-color) 100%);
        }

        .alert {
            border-radius: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="checkin-card">
                    <div class="logo">
                        <i class="fas fa-dumbbell"></i>
                        <h2 class="mt-3">Gym Check-In</h2>
                        <p class="text-muted">Enter your Member ID to check in</p>
                    </div>

                    <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                        <br><small>Redirecting to your dashboard...</small>
                    </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="text"
                               class="form-control checkin-input"
                               name="member_id"
                               placeholder="Enter Member ID"
                               autocomplete="off"
                               autofocus
                               required>
                        <button type="submit" name="checkin" class="btn btn-checkin">
                            <i class="fas fa-sign-in-alt me-2"></i>CHECK IN
                        </button>
                    </form>

                    <div class="text-center mt-4">
                        <small class="text-muted">Scan your QR code or enter your ID manually</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
