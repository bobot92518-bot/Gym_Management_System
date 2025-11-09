<?php
// logout.php
session_start();

// Member logout - no attendance check-out needed

session_destroy();

// Redirect based on user type
if (isset($_SESSION['user_id'])) {
    // Staff/Admin user
    header('Location: login.php');
} else {
    // Member
    header('Location: ../landing.php');
}
exit();
?>
