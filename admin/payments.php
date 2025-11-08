    <?php
// payments.php - Payments Management
require_once '../config.php';
require_once '../session.php';
requireLogin();

$db = new Database();
$conn = $db->connect();

// Fetch all payments
$search = isset($_GET['search']) ? $_GET['search'] : '';
$query = "SELECT * FROM payments WHERE 1=1";

if ($search) {
    $query .= " AND (id LIKE '%$search%' OR member_id LIKE '%$search%' OR subscription_id LIKE '%$search%' OR payment_method LIKE '%$search%' OR description LIKE '%$search%')";
}

$query .= " ORDER BY created_at DESC";

$stmt = $conn->query($query);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payments - Gym Management</title>
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
    .card-header {
        background: white;
        border-bottom: 2px solid var(--primary-color);
        font-weight: 600;
        border-radius: 15px 15px 0 0 !important;
    }
    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
    }
    .btn-primary:hover {
        background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
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
            <a class="nav-link" href="attendance.php">
                <i class="fas fa-clipboard-check me-2"></i> Attendance
            </a>
            <a class="nav-link" href="subscriptions.php">
                <i class="fas fa-id-card me-2"></i> Subscriptions
            </a>
            <a class="nav-link active" href="payments.php">
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
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-dollar-sign me-2"></i>Payments</h2>
    </div>

    <!-- Search -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search" placeholder="Search by ID, member ID, subscription, method, description" value="<?php echo $search; ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-2"></i>Search</button>
                </div>
                <div class="col-md-2">
                    <a href="payments.php" class="btn btn-outline-secondary w-100"><i class="fas fa-redo me-2"></i>Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Payments Table -->
    <div class="card">
        <div class="card-header"><i class="fas fa-list me-2"></i>All Payments (<?php echo count($payments); ?>)</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Member ID</th>
                            <th>Subscription ID</th>
                            <th>Amount</th>
                            <th>Payment Date</th>
                            <th>Payment Method</th>
                            <th>Description</th>
                            <th>Created By</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?php echo $payment['id']; ?></td>
                            <td><?php echo $payment['member_id']; ?></td>
                            <td><?php echo $payment['subscription_id']; ?></td>
                            <td>$<?php echo $payment['amount']; ?></td>
                            <td><?php echo $payment['payment_date']; ?></td>
                            <td><?php echo $payment['payment_method']; ?></td>
                            <td><?php echo $payment['description']; ?></td>
                            <td><?php echo $payment['created_by']; ?></td>
                            <td><?php echo $payment['created_at']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted">No payments found</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
