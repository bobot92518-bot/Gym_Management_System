<?php
// login.php - Login and Registration Page
require_once 'config.php';
session_start();

$login_error = '';
$register_error = '';
$register_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    $conn = $db->connect();

    if (isset($_POST['login'])) {
        // Login logic
        $username = trim($_POST['login_username']);
        $password = $_POST['login_password'];

        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];

            header('Location: admin/index.php');
            exit();
        } else {
            $login_error = 'Invalid username or password';
        }
    } elseif (isset($_POST['register'])) {
        // Registration logic
        $username = trim($_POST['register_username']);
        $password = $_POST['register_password'];
        $confirm_password = $_POST['confirm_password'];
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);

        // Validation
        if (empty($username) || empty($password) || empty($full_name)) {
            $register_error = 'Please fill in all required fields';
        } elseif (strlen($password) < 6) {
            $register_error = 'Password must be at least 6 characters long';
        } elseif ($password !== $confirm_password) {
            $register_error = 'Passwords do not match';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $register_error = 'Please enter a valid email address';
        } else {
            // Check if username already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $register_error = 'Username already exists';
            } else {
                // Check if email already exists
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $register_error = 'Email already exists';
                } else {
                    // Create new user
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, 'staff')");
                    try {
                        if ($stmt->execute([$username, $hashed_password, $full_name, $email])) {
                            $register_success = 'Registration successful! You can now login.';
                        } else {
                            $register_error = 'Registration failed. Please try again.';
                        }
                    } catch (PDOException $e) {
                        $register_error = 'Database error: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Gym Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            max-width: 450px;
            margin: auto;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .login-header i {
            font-size: 4rem;
            margin-bottom: 15px;
        }
        
        .login-body {
            padding: 40px 30px;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.3s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="login-card">
                <div class="login-header">
                    <i class="fas fa-dumbbell"></i>
                    <h3>GYM Management System</h3>
                    <p class="mb-0">Please login to continue</p>
                </div>
                <div class="login-body">
                    <!-- Nav tabs -->
                    <ul class="nav nav-tabs nav-justified mb-4" id="authTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#login" type="button" role="tab" aria-controls="login" aria-selected="true">
                                <i class="fas fa-sign-in-alt me-2"></i>Login
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="register-tab" data-bs-toggle="tab" data-bs-target="#register" type="button" role="tab" aria-controls="register" aria-selected="false">
                                <i class="fas fa-user-plus me-2"></i>Sign Up
                            </button>
                        </li>
                    </ul>

                    <!-- Tab content -->
                    <div class="tab-content" id="authTabsContent">
                        <!-- Login Tab -->
                        <div class="tab-pane fade show active" id="login" role="tabpanel" aria-labelledby="login-tab">
                            <?php if ($login_error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $login_error; ?>
                            </div>
                            <?php endif; ?>

                            <form method="POST">
                                <input type="hidden" name="login" value="1">
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <input type="text" class="form-control" name="login_username" required autofocus autocomplete="username">
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label">Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control" name="login_password" required autocomplete="current-password">
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-login w-100">
                                    <i class="fas fa-sign-in-alt me-2"></i>Login
                                </button>
                            </form>

                            <div class="text-center mt-4">
                                <small class="text-muted">
                                    Demo: username: <strong>admin</strong> | password: <strong>admin123</strong>
                                </small>
                            </div>
                        </div>

                        <!-- Register Tab -->
                        <div class="tab-pane fade" id="register" role="tabpanel" aria-labelledby="register-tab">
                            <?php if ($register_error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $register_error; ?>
                            </div>
                            <?php endif; ?>

                            <?php if ($register_success): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?php echo $register_success; ?>
                            </div>
                            <?php endif; ?>

                            <form method="POST">
                                <input type="hidden" name="register" value="1">
                                <div class="mb-3">
                                    <label class="form-label">Username *</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <input type="text" class="form-control" name="register_username" required autocomplete="username">
                                    </div>
                                    <small class="form-text text-muted">Choose a unique username</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Full Name *</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                        <input type="text" class="form-control" name="full_name" required autocomplete="name">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email *</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                        <input type="email" class="form-control" name="email" required autocomplete="email">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Password *</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control" name="register_password" required minlength="6" autocomplete="new-password">
                                    </div>
                                    <small class="form-text text-muted">Minimum 6 characters</small>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label">Confirm Password *</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control" name="confirm_password" required minlength="6" autocomplete="new-password">
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-login w-100">
                                    <i class="fas fa-user-plus me-2"></i>Sign Up
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
