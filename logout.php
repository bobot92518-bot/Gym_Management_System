<?php
// logout.php
session_start();

// If member is logged out, update their attendance check-out time
if (isset($_SESSION['member_id'])) {
    require_once 'config.php';
    $db = new Database();
    $conn = $db->connect();

    // Update check-out time for today's attendance
    $stmt = $conn->prepare("UPDATE attendance SET check_out_time = NOW() WHERE member_id = ? AND date = CURDATE() AND check_out_time IS NULL");
    $stmt->execute([$_SESSION['member_id']]);
}

session_destroy();

// Redirect based on user type
if (isset($_SESSION['user_id'])) {
    // Staff/Admin user
    header('Location: login.php');
} else {
    // Member
    header('Location: member_landing.php');
}
exit();
?>
