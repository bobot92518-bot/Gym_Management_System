<?php
// payments.php - POS Payment Management
require_once '../config.php';
require_once '../session.php';
requireLogin();

$db = new Database();
$conn = $db->connect();

$message = '';
$messageType = '';

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    try {
        $member_id = !empty($_POST['member_id']) ? intval($_POST['member_id']) : NULL;
        $subscription_id = !empty($_POST['subscription_id']) ? intval($_POST['subscription_id']) : NULL;
        $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
        $payment_date = filter_input(INPUT_POST, 'payment_date', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $payment_method = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        if (!$member_id) {
            throw new Exception("Member selection is required");
        }

        if (!$subscription_id) {
            throw new Exception("Please select a pending subscription to pay");
        }

        if (!$amount || $amount <= 0) {
            throw new Exception("Valid amount required");
        }

        if (!$payment_date || !$payment_method) {
            throw new Exception("All required fields must be filled");
        }

        // Verify subscription belongs to member and is pending
        $stmt = $conn->prepare("SELECT status FROM subscriptions WHERE id = ? AND member_id = ?");
        $stmt->execute([$subscription_id, $member_id]);
        $sub = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sub) {
            throw new Exception("Invalid subscription for this member");
        }

        // Calculate discount if applied
        $discount_id = !empty($_POST['discount_id']) ? intval($_POST['discount_id']) : NULL;
        $discount_amount = 0;
        $final_amount = $amount;

        if ($discount_id) {
            $discountStmt = $conn->prepare("SELECT discount_type, discount_value, min_amount, max_discount FROM discounts WHERE id = ? AND status = 'Active'");
            $discountStmt->execute([$discount_id]);
            $discount = $discountStmt->fetch(PDO::FETCH_ASSOC);

            if ($discount) {
                // Check minimum amount requirement
                if ($discount['min_amount'] && $amount < $discount['min_amount']) {
                    throw new Exception("Discount requires a minimum amount of ₱" . number_format($discount['min_amount'], 2));
                }

                // Calculate discount based on type
                if ($discount['discount_type'] === 'Percentage') {
                    $discount_amount = ($amount * $discount['discount_value']) / 100;
                } else {
                    $discount_amount = $discount['discount_value'];
                }

                // Apply maximum discount cap if set
                if ($discount['max_discount'] && $discount_amount > $discount['max_discount']) {
                    $discount_amount = $discount['max_discount'];
                }

                $final_amount = $amount - $discount_amount;
            }
        }

        // Check if discount columns exist in payments table
        $columnsCheck = $conn->query("SHOW COLUMNS FROM payments LIKE 'discount_id'");
        $hasDiscountColumns = $columnsCheck->rowCount() > 0;

        // Determine a valid created_by (session user) or fallback to an admin if available
        $created_by = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
        if ($created_by) {
            $userCheck = $conn->prepare("SELECT id FROM users WHERE id = ?");
            $userCheck->execute([$created_by]);
            if ($userCheck->rowCount() === 0) {
                $created_by = null;
            }
        }

        if (!$created_by) {
            // attempt to fallback to an admin user if present
            try {
                $adminStmt = $conn->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
                $admin = $adminStmt->fetch(PDO::FETCH_ASSOC);
                if ($admin && isset($admin['id'])) {
                    $created_by = intval($admin['id']);
                }
            } catch (Exception $e) {
                // ignore and allow null fallback
                $created_by = null;
            }
        }

        // Insert payment with or without discount columns
        if ($hasDiscountColumns) {
            if ($created_by !== null) {
                $stmt = $conn->prepare("INSERT INTO payments (member_id, subscription_id, amount, discount_id, discount_amount, final_amount, payment_date, payment_method, description, created_by, created_at) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([
                    $member_id,
                    $subscription_id,
                    $amount,
                    $discount_id,
                    $discount_amount,
                    $final_amount,
                    $payment_date,
                    $payment_method,
                    $description,
                    $created_by
                ]);
            } else {
                // created_by not available; omit it from insert (assuming column allows NULL)
                $stmt = $conn->prepare("INSERT INTO payments (member_id, subscription_id, amount, discount_id, discount_amount, final_amount, payment_date, payment_method, description, created_at) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([
                    $member_id,
                    $subscription_id,
                    $amount,
                    $discount_id,
                    $discount_amount,
                    $final_amount,
                    $payment_date,
                    $payment_method,
                    $description
                ]);
            }
        } else {
            // Legacy insert without discount columns
            if ($created_by !== null) {
                $stmt = $conn->prepare("INSERT INTO payments (member_id, subscription_id, amount, payment_date, payment_method, description, created_by, created_at) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([
                    $member_id,
                    $subscription_id,
                    $final_amount,
                    $payment_date,
                    $payment_method,
                    $description,
                    $created_by
                ]);
            } else {
                // created_by not available; omit it from insert (assuming column allows NULL)
                $stmt = $conn->prepare("INSERT INTO payments (member_id, subscription_id, amount, payment_date, payment_method, description, created_at) 
                                       VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([
                    $member_id,
                    $subscription_id,
                    $final_amount,
                    $payment_date,
                    $payment_method,
                    $description
                ]);
            }
        }

        // Update subscription status to Active and record payment details
        $stmt = $conn->prepare("UPDATE subscriptions SET status = 'Active', amount_paid = ?, payment_method = ? WHERE id = ?");
        $stmt->execute([$final_amount, $payment_method, $subscription_id]);

        $message = "Payment processed successfully! Subscription activated ✓";
        $messageType = "success";
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "danger";
    }
}

if (isset($_GET['delete_id'])) {
    try {
        $payment_id = intval($_GET['delete_id']);
        $stmt = $conn->prepare("DELETE FROM payments WHERE id = ?");
        $stmt->execute([$payment_id]);
        $message = "Payment deleted successfully!";
        $messageType = "success";
    } catch (Exception $e) {
        $message = "Error deleting payment: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Fetch all payments
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Check if discount columns exist in payments table
$columnsCheck = $conn->query("SHOW COLUMNS FROM payments LIKE 'discount_id'");
$hasDiscountColumns = $columnsCheck->rowCount() > 0;

if ($hasDiscountColumns) {
    $query = "SELECT p.*, d.discount_name FROM payments p LEFT JOIN discounts d ON p.discount_id = d.id WHERE 1=1";
} else {
    $query = "SELECT p.* FROM payments p WHERE 1=1";
}

$params = [];

if ($search) {
    $query .= " AND (p.id LIKE ? OR p.member_id LIKE ? OR p.subscription_id LIKE ? OR p.payment_method LIKE ? OR p.description LIKE ?)";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm];
}

$query .= " ORDER BY p.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch members for dropdown
$membersStmt = $conn->query("SELECT id, member_id, CONCAT(first_name, ' ', last_name) as full_name FROM members WHERE status = 'Active' ORDER BY first_name");
$members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch active discounts for dropdown (if table exists)
$discounts = [];
if ($hasDiscountColumns) {
    try {
        $discountsStmt = $conn->query("SELECT id, discount_name, discount_type, discount_value FROM discounts WHERE status = 'Active' ORDER BY discount_name");
        $discounts = $discountsStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $discounts = [];
    }
}
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

    * {
        margin: 0;
        padding: 0;
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
        text-decoration: none;
        display: block;
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

    .pos-container {
        display: grid;
        grid-template-columns: 1fr 400px;
        gap: 20px;
        max-width: 100%;
        margin: 0 auto;
    }

    .history-section {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        min-height: 100%;
    }

    .section-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 16px 18px;
        border-radius: 8px;
        margin-bottom: 18px;
        font-weight: 600;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 2px 6px rgba(102, 126, 234, 0.2);
    }

    .search-box {
        margin-bottom: 18px;
        display: flex;
        gap: 10px;
    }

    .search-box input {
        flex: 1;
        padding: 10px 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 13px;
        transition: border-color 0.3s;
    }

    .search-box input:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.1);
    }

    .search-box button {
        padding: 10px 18px;
        background: #667eea;
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s;
    }

    .search-box button:hover {
        background: #764ba2;
        transform: translateY(-1px);
        box-shadow: 0 2px 6px rgba(102, 126, 234, 0.3);
    }

    .payments-table {
        overflow-x: auto;
        overflow-y: auto;
        max-height: 80vh;
        border-radius: 6px;
    }

    table {
        width: 100%;
        font-size: 13px;
        border-collapse: collapse;
        background: white;
    }

    thead th {
        background: #f8f9fa;
        padding: 12px 10px;
        text-align: left;
        font-weight: 700;
        color: #2c3e50;
        border-bottom: 2px solid #e0e0e0;
        position: sticky;
        top: 0;
        z-index: 10;
        white-space: nowrap;
    }

    tbody td {
        padding: 11px 10px;
        border-bottom: 1px solid #f0f0f0;
        white-space: nowrap;
    }

    tbody tr:hover {
        background: #f9f9f9;
    }

    .payment-amount {
        font-weight: 700;
        color: #48bb78;
    }

    .payment-method {
        display: inline-block;
        background: #bee3f8;
        color: #2c5282;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 700;
    }

    .btn-small {
        padding: 5px 8px;
        margin: 0 2px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 11px;
        font-weight: 600;
        transition: all 0.3s;
    }

    .btn-delete {
        background: #fed7d7;
        color: #742a2a;
    }

    .btn-delete:hover {
        background: #fc8181;
        color: white;
        transform: scale(1.08);
    }

    .form-section {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        max-height: 90vh;
        display: flex;
        flex-direction: column;
        position: sticky;
        top: 20px;
    }

    .form-title {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 14px 16px;
        border-radius: 8px;
        margin: -20px -20px 16px -20px;
        font-weight: 700;
        text-align: center;
        box-shadow: 0 2px 6px rgba(102, 126, 234, 0.2);
    }

    .form-group {
        margin-bottom: 14px;
    }

    .form-group label {
        display: block;
        font-size: 12px;
        font-weight: 700;
        margin-bottom: 5px;
        color: #2c3e50;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 9px 10px;
        font-size: 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        box-sizing: border-box;
        font-family: inherit;
        transition: border-color 0.3s;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.1);
    }

    .payment-summary {
        background: #f8f9fa;
        padding: 13px;
        border-radius: 6px;
        margin-bottom: 16px;
        border-left: 3px solid #48bb78;
    }

    .summary-row {
        display: flex;
        justify-content: space-between;
        font-size: 12px;
        margin-bottom: 6px;
        font-weight: 600;
    }

    .btn-submit {
        width: 100%;
        padding: 12px;
        background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
        color: white;
        border: none;
        border-radius: 6px;
        font-weight: 700;
        cursor: pointer;
        font-size: 13px;
        transition: all 0.3s;
        margin-top: auto;
        box-shadow: 0 2px 6px rgba(72, 187, 120, 0.2);
    }

    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(72, 187, 120, 0.4);
    }

    .btn-submit:active {
        transform: translateY(0);
    }

    .message {
        padding: 12px 14px;
        border-radius: 6px;
        margin-bottom: 16px;
        font-size: 12px;
        border-left: 3px solid;
        animation: slideDown 0.3s ease-out;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .message.success {
        background: #c6f6d5;
        color: #22543d;
        border-left-color: #48bb78;
    }

    .message.danger {
        background: #fed7d7;
        color: #742a2a;
        border-left-color: #f56565;
    }

    @media (max-width: 1366px) {
        .pos-container {
            grid-template-columns: 1fr 380px;
        }
    }

    @media (max-width: 1200px) {
        .pos-container {
            grid-template-columns: 1fr 350px;
        }
        .form-section {
            top: 0;
        }
    }

    @media (max-width: 992px) {
        .main-content {
            margin-left: 250px;
            padding: 15px;
        }

        .pos-container {
            grid-template-columns: 1fr;
            gap: 15px;
        }

        .form-section {
            max-height: auto;
            position: relative;
            top: auto;
        }

        table {
            font-size: 12px;
        }

        tbody td {
            padding: 8px;
        }
    }

    @media (max-width: 768px) {
        .sidebar {
            width: 200px;
        }

        .main-content {
            margin-left: 200px;
            padding: 10px;
        }

        table {
            font-size: 11px;
        }

        thead th {
            padding: 8px 6px;
        }

        tbody td {
            padding: 6px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            font-size: 14px;
        }
    }

    .empty-state {
        text-align: center;
        color: #bbb;
        padding: 60px 20px;
        font-size: 14px;
    }

    .empty-state i {
        font-size: 50px;
        display: block;
        margin-bottom: 15px;
        opacity: 0.5;
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
        <a class="nav-link" href="discounts.php">
            <i class="fas fa-tag me-2"></i> Discounts
        </a>
        <a class="nav-link" href="reports.php">
            <i class="fas fa-chart-bar me-2"></i> Reports
        </a>

        <?php if ($_SESSION['role'] === 'admin'): ?>
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
            <h2 style="margin: 0;"><i class="fas fa-cash-register me-2"></i>Payment Management</h2>
            <small style="color: #999;">Today: <?php echo date('d M Y'); ?></small>
        </div>    <div class="pos-container">
    <!-- Payment History -->
    <div class="history-section">
        <div class="section-header">
            <span><i class="fas fa-history me-2"></i>Payment History</span>
            <a href="index.php" style="color: white; text-decoration: none; font-size: 12px;">
                <i class="fas fa-home"></i>
            </a>
        </div>

        <div class="search-box">
            <form method="GET" style="display: flex; gap: 10px; width: 100%;">
                <input type="text" name="search" placeholder="Search by ID, member, method..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit"><i class="fas fa-search"></i></button>
            </form>
        </div>

        <div class="payments-table">
            <?php if (count($payments) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Member</th>
                            <th>Amount</th>
                            <?php if ($hasDiscountColumns): ?>
                            <th>Discount</th>
                            <th>Final Amount</th>
                            <?php endif; ?>
                            <th>Method</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><strong><?php echo $payment['id']; ?></strong></td>
                            <td><?php echo htmlspecialchars($payment['member_id'] ?? 'N/A'); ?></td>
                            <td class="payment-amount">₱<?php echo number_format($payment['amount'], 2); ?></td>
                            <?php if ($hasDiscountColumns): ?>
                            <td>
                                <?php if (isset($payment['discount_name']) && $payment['discount_name']): ?>
                                    <span style="font-size: 11px; background: #fef3c7; color: #92400e; padding: 3px 6px; border-radius: 4px;">
                                        <?php echo htmlspecialchars($payment['discount_name']); ?><br>
                                        <strong>-₱<?php echo number_format($payment['discount_amount'] ?? 0, 2); ?></strong>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #999; font-size: 11px;">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="payment-amount" style="color: #48bb78; font-weight: bold;">₱<?php echo number_format($payment['final_amount'] ?? $payment['amount'], 2); ?></td>
                            <?php endif; ?>
                            <td><span class="payment-method"><?php echo htmlspecialchars($payment['payment_method']); ?></span></td>
                            <td><?php echo date('d-M H:i', strtotime($payment['payment_date'])); ?></td>
                            <td>
                                <a href="?delete_id=<?php echo $payment['id']; ?>" class="btn-small btn-delete" onclick="return confirm('Delete?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-receipt" style="font-size: 40px; display: block; margin-bottom: 10px;"></i>
                    No payments found
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Payment Form (POS Counter) -->
    <div class="form-section">
        <div class="form-title">
            <i class="fas fa-cash-register me-2"></i>PAYMENT COUNTER
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <i class="fas fa-<?php echo ($messageType === 'success' ? 'check-circle' : 'exclamation-circle'); ?> me-2"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST" style="flex: 1; display: flex; flex-direction: column;">
            <div class="form-group">
                <label><i class="fas fa-user me-1"></i>Member ID *</label>
                <select name="member_id" id="memberSelect" required onchange="loadPendingSubscriptions()">
                    <option value="">-- Select Member --</option>
                    <?php foreach ($members as $member): ?>
                        <option value="<?php echo $member['id']; ?>">
                            <?php echo htmlspecialchars($member['member_id'] . ' - ' . $member['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label><i class="fas fa-id-card me-1"></i>Pending Subscription *</label>
                <select name="subscription_id" id="subscriptionSelect" required>
                    <option value="">-- Select a pending subscription --</option>
                </select>
                <small style="color: #999; display: block; margin-top: 4px;">
                    <i class="fas fa-info-circle"></i> Only unpaid subscriptions shown
                </small>
            </div>

            <div class="form-group">
                <label><i class="fas fa-dollar-sign me-1"></i>Amount *</label>
                <input type="number" step="0.01" name="amount" id="amountInput" placeholder="0.00" required readonly style="background: #f0f0f0; cursor: not-allowed;">
            </div>

            <div class="form-group">
                <label><i class="fas fa-calendar me-1"></i>Payment Date *</label>
                <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>

            <div class="form-group">
                <label><i class="fas fa-credit-card me-1"></i>Payment Method *</label>
                <select name="payment_method" required>
                    <option value="">-- Select Method --</option>
                    <option value="Cash">💵 Cash</option>
                    <option value="Credit Card">💳 Credit Card</option>
                    <option value="Debit Card">💳 Debit Card</option>
                    <option value="Bank Transfer">🏦 Bank Transfer</option>
                    <option value="Check">✓ Check</option>
                </select>
            </div>

            <?php if ($hasDiscountColumns): ?>
            <div class="form-group">
                <label><i class="fas fa-tag me-1"></i>Discount (Optional)</label>
                <select name="discount_id" id="discountSelect" onchange="calculateDiscount()">
                    <option value="">-- No Discount --</option>
                    <?php foreach ($discounts as $discount): ?>
                        <option value="<?php echo $discount['id']; ?>" data-type="<?php echo $discount['discount_type']; ?>" data-value="<?php echo $discount['discount_value']; ?>">
                            <?php echo htmlspecialchars($discount['discount_name']); ?> 
                            (<?php echo $discount['discount_type'] === 'Percentage' ? $discount['discount_value'] . '%' : '₱' . number_format($discount['discount_value'], 2); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <small style="color: #999; display: block; margin-top: 4px;">
                    <i class="fas fa-info-circle"></i> Discount will be applied to final amount
                </small>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label><i class="fas fa-sticky-note me-1"></i>Description</label>
                <textarea name="description" rows="2" placeholder="Order notes..."></textarea>
            </div>

            <div class="payment-summary" style="margin-top: auto;">
                <div class="summary-row">
                    <span><i class="fas fa-info-circle" style="color: #4299e1;"></i> Payment will activate subscription</span>
                </div>
                <?php if ($hasDiscountColumns): ?>
                <div class="summary-row" id="discount-summary" style="display: none; margin-top: 8px; padding-top: 8px; border-top: 1px solid #ddd;">
                    <span>Discount:</span>
                    <span id="discount-display"></span>
                </div>
                <div class="summary-row" id="final-amount-summary" style="display: none; margin-top: 8px;">
                    <span><strong>Final Amount:</strong></span>
                    <span id="final-amount-display" style="color: #48bb78; font-weight: bold;"></span>
                </div>
                <?php endif; ?>
            </div>

            <button type="submit" name="process_payment" class="btn-submit">
                <i class="fas fa-check me-2"></i>Process Payment
            </button>
        </form>

        <script>
        function calculateDiscount() {
            const discountSelect = document.getElementById('discountSelect');
            const amountInput = document.getElementById('amountInput');
            const discountSummary = document.getElementById('discount-summary');
            const discountDisplay = document.getElementById('discount-display');
            const finalAmountSummary = document.getElementById('final-amount-summary');
            const finalAmountDisplay = document.getElementById('final-amount-display');

            const selectedOption = discountSelect.options[discountSelect.selectedIndex];
            const originalAmount = parseFloat(amountInput.value) || 0;

            if (!discountSelect.value || originalAmount <= 0) {
                discountSummary.style.display = 'none';
                finalAmountSummary.style.display = 'none';
                return;
            }

            const discountType = selectedOption.dataset.type;
            const discountValue = parseFloat(selectedOption.dataset.value);
            let discountAmount = 0;

            if (discountType === 'Percentage') {
                discountAmount = (originalAmount * discountValue) / 100;
            } else {
                discountAmount = discountValue;
            }

            const finalAmount = Math.max(0, originalAmount - discountAmount);

            discountDisplay.textContent = '-₱' + discountAmount.toFixed(2);
            finalAmountDisplay.textContent = '₱' + finalAmount.toFixed(2);

            discountSummary.style.display = 'flex';
            finalAmountSummary.style.display = 'flex';
        }

        function loadPendingSubscriptions(memberId = null, selectedSubscriptionId = null) {
            const memberSelect = document.getElementById('memberSelect');
            const subscriptionSelect = document.getElementById('subscriptionSelect');
            const amountInput = document.getElementById('amountInput');

            // Use provided memberId or get from select
            const targetMemberId = memberId || memberSelect.value;

            subscriptionSelect.innerHTML = '<option value="">-- Select a pending subscription --</option>';
            amountInput.value = '';

            if (!targetMemberId) return;

            // Fetch pending subscriptions for this member
            fetch('get_pending_subscriptions.php?member_id=' + targetMemberId)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        subscriptionSelect.innerHTML = '<option value="">Error loading subscriptions</option>';
                        return;
                    }

                    if (data.subscriptions.length === 0) {
                        subscriptionSelect.innerHTML = '<option value="">No pending subscriptions</option>';
                        return;
                    }

                    let options = '<option value="">-- Select a pending subscription --</option>';
                    data.subscriptions.forEach(sub => {
                        const selected = (selectedSubscriptionId && sub.id == selectedSubscriptionId) ? 'selected' : '';
                        options += `<option value="${sub.id}" data-amount="${sub.amount_due}" ${selected}>
                            Plan: ${sub.plan_name} | ₱${parseFloat(sub.amount_due).toFixed(2)} (Due: ${sub.end_date})
                        </option>`;
                    });
                    subscriptionSelect.innerHTML = options;

                    // Auto-select subscription and fill amount if specified
                    if (selectedSubscriptionId) {
                        const selectedOption = subscriptionSelect.querySelector(`option[value="${selectedSubscriptionId}"]`);
                        if (selectedOption) {
                            amountInput.value = selectedOption.dataset.amount || '';
                            calculateDiscount();
                        }
                    }

                    // Update amount when subscription is selected
                    subscriptionSelect.onchange = function() {
                        const selected = this.options[this.selectedIndex];
                        amountInput.value = selected.dataset.amount || '';
                        calculateDiscount();
                    };
                })
                .catch(error => {
                    console.error('Error:', error);
                    subscriptionSelect.innerHTML = '<option value="">Error loading subscriptions</option>';
                });
        }

        // Handle URL parameters for pre-selection
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const memberId = urlParams.get('member_id');
            const subscriptionId = urlParams.get('subscription_id');

            if (memberId) {
                // Pre-select member
                const memberSelect = document.getElementById('memberSelect');
                memberSelect.value = memberId;

                // Load subscriptions and pre-select if subscription_id provided
                loadPendingSubscriptions(memberId, subscriptionId);
            }
        });
        </script>
    </div>
</div>
</div>

</body>
</html>
