<?php
// payments.php - Payments POS
// payments.php - POS Payment Management
require_once '../config.php';
require_once '../session.php';
requireLogin();

$db = new Database();
$conn = $db->connect();

// Handle AJAX request for unpaid plans
if (isset($_GET['action']) && $_GET['action'] === 'get_unpaid_subscriptions' && isset($_GET['member_id'])) {
    $member_id = $_GET['member_id'];
    $stmt = $conn->prepare("SELECT mp.id as plan_id, mp.plan_name, SUM(s.amount_paid) as total_unpaid, COUNT(s.id) as subscription_count
                           FROM subscriptions s
                           JOIN membership_plans mp ON s.plan_id = mp.id
                           WHERE s.member_id = ? AND s.status = 'Pending'
                           GROUP BY mp.id, mp.plan_name");
    $stmt->execute([$member_id]);
    $unpaidPlans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate total unpaid balance
    $totalBalance = 0;
    foreach ($unpaidPlans as $plan) {
        $totalBalance += $plan['total_unpaid'];
    }

    $response = [
        'plans' => $unpaidPlans,
        'total_balance' => $totalBalance
    ];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Handle payment processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add_payment') {
        try {
            $stmt = $conn->prepare("INSERT INTO payments (member_id, subscription_id, amount, payment_date, payment_method, description, created_by, created_at) VALUES (?, ?, ?, CURDATE(), ?, ?, ?, NOW())");
            $stmt->execute([
                $_POST['member_id'],
                !empty($_POST['subscription_id']) ? $_POST['subscription_id'] : null,
                $_POST['amount'],
                $_POST['payment_method'],
                $_POST['description'],
                $_SESSION['user_id'] ?? 1 // Default to 1 if not set
            ]);
            $success = "Payment processed successfully!";
        } catch (Exception $e) {
            $error = "Error processing payment: " . $e->getMessage();
        }
    }
}

// Fetch pending subscriptions
$pendingQuery = "SELECT s.*, m.first_name, m.last_name, m.member_id as member_code, mp.plan_name
                 FROM subscriptions s
                 JOIN members m ON s.member_id = m.id
                 JOIN membership_plans mp ON s.plan_id = mp.id
                 WHERE s.status = 'Pending'
                 ORDER BY s.created_at DESC";
$pendingStmt = $conn->query($pendingQuery);
$pendingSubscriptions = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);
$message = '';
$messageType = '';

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    try {
        $member_id = !empty($_POST['member_id']) ? intval($_POST['member_id']) : NULL;
        $subscription_id = !empty($_POST['subscription_id']) ? intval($_POST['subscription_id']) : NULL;
        $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
        $payment_date = filter_input(INPUT_POST, 'payment_date', FILTER_SANITIZE_STRING);
        $payment_method = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_STRING);
        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);

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

        // Insert payment
        $stmt = $conn->prepare("INSERT INTO payments (member_id, subscription_id, amount, payment_date, payment_method, description, created_by, created_at) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $member_id,
            $subscription_id,
            $amount,
            $payment_date,
            $payment_method,
            $description,
            $_SESSION['user_id']
        ]);

        // Update subscription status to Active and record payment details
        $stmt = $conn->prepare("UPDATE subscriptions SET status = 'Active', amount_paid = ?, payment_method = ? WHERE id = ?");
        $stmt->execute([$amount, $payment_method, $subscription_id]);

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
$query = "SELECT * FROM payments WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (id LIKE ? OR member_id LIKE ? OR subscription_id LIKE ? OR payment_method LIKE ? OR description LIKE ?)";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm];
}

$query .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch members for dropdown
$membersStmt = $conn->query("SELECT id, member_id, CONCAT(first_name, ' ', last_name) as full_name FROM members WHERE status = 'Active' ORDER BY first_name");
$members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payments POS - Gym Management</title>
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
        grid-template-columns: 1fr 380px;
        gap: 15px;
        max-width: 1600px;
        margin: 0 auto;
    }

    .history-section {
        background: white;
        border-radius: 15px;
        padding: 20px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    }

    .section-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-weight: 600;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .search-box {
        margin-bottom: 15px;
        display: flex;
        gap: 10px;
    }

    .search-box input {
        flex: 1;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 13px;
    }

    .search-box button {
        padding: 10px 20px;
        background: #667eea;
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
    }

    .search-box button:hover {
        background: #764ba2;
    }

    .payments-table {
        overflow-y: auto;
        max-height: 85vh;
    }

    table {
        width: 100%;
        font-size: 12px;
        border-collapse: collapse;
    }

    thead th {
        background: #f8f9fa;
        padding: 10px;
        text-align: left;
        font-weight: 600;
        color: #2c3e50;
        border-bottom: 2px solid #ddd;
        position: sticky;
        top: 0;
    }

    tbody td {
        padding: 10px;
        border-bottom: 1px solid #eee;
    }

    tbody tr:hover {
        background: #f8f9fa;
    }

    .payment-amount {
        font-weight: 700;
        color: #48bb78;
    }

    .payment-method {
        display: inline-block;
        background: #bee3f8;
        color: #2c5282;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
    }

    .btn-small {
        padding: 4px 8px;
        margin: 0 2px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 11px;
        font-weight: 600;
    }

    .btn-delete {
        background: #fed7d7;
        color: #742a2a;
    }

    .form-section {
        background: white;
        border-radius: 15px;
        padding: 20px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        max-height: 85vh;
        display: flex;
        flex-direction: column;
    }

    .form-title {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 15px;
        border-radius: 8px;
        margin: -20px -20px 15px -20px;
        font-weight: 600;
        text-align: center;
    }

    .form-group {
        margin-bottom: 12px;
    }

    .form-group label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        margin-bottom: 4px;
        color: #2c3e50;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 8px;
        font-size: 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        box-sizing: border-box;
        font-family: inherit;
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
        padding: 12px;
        border-radius: 6px;
        margin-bottom: 15px;
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
        font-weight: 600;
        cursor: pointer;
        font-size: 13px;
        transition: all 0.3s;
        margin-top: 10px;
    }

    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(72, 187, 120, 0.3);
    }

    .message {
        padding: 12px;
        border-radius: 6px;
        margin-bottom: 15px;
        font-size: 12px;
    }

    .message.success {
        background: #c6f6d5;
        color: #22543d;
    }

    .message.danger {
        background: #fed7d7;
        color: #742a2a;
    }

    @media (max-width: 1200px) {
        .pos-container {
            grid-template-columns: 1fr;
        }
        .form-section {
            max-height: auto;
        }
    }

    .empty-state {
        text-align: center;
        color: #bbb;
        padding: 40px 20px;
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
        <h2><i class="fas fa-cash-register me-2"></i>Payments POS</h2>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
            <i class="fas fa-plus me-2"></i>Process New Payment
        </button>
    </div>

    <?php if (isset($success)): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Pending Payments Section -->
    <?php if (!empty($pendingSubscriptions)): ?>
    <div class="card mb-4">
        <div class="card-header bg-warning text-dark">
            <i class="fas fa-clock me-2"></i>Pending Payments (<?php echo count($pendingSubscriptions); ?>)
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Plan</th>
                            <th>Amount Due</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingSubscriptions as $sub): ?>
                        <tr>
                            <td>
                                <strong><?php echo $sub['first_name'] . ' ' . $sub['last_name']; ?></strong><br>
                                <small class="text-muted">ID: <?php echo $sub['member_code']; ?></small>
                            </td>
                            <td><?php echo $sub['plan_name']; ?></td>
                            <td>$<?php echo $sub['amount_paid']; ?></td>
                            <td><?php echo $sub['start_date']; ?></td>
                            <td><?php echo $sub['end_date']; ?></td>
                            <td>
                                <button class="btn btn-sm btn-success" onclick="processPendingPayment(<?php echo $sub['id']; ?>, <?php echo $sub['member_id']; ?>, <?php echo $sub['amount_paid']; ?>)">
                                    <i class="fas fa-credit-card me-1"></i>Process Payment
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

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
        <h2><i class="fas fa-cash-register me-2"></i>Payment Management</h2>
    </div>

    <div class="pos-container">
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
                            <td class="payment-amount">₹<?php echo number_format($payment['amount'], 2); ?></td>
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

            <div class="form-group">
                <label><i class="fas fa-sticky-note me-1"></i>Description</label>
                <textarea name="description" rows="2" placeholder="Order notes..."></textarea>
            </div>

            <div class="payment-summary" style="margin-top: auto;">
                <div class="summary-row">
                    <span><i class="fas fa-info-circle" style="color: #4299e1;"></i> Payment will activate subscription</span>
                </div>
            </div>

            <button type="submit" name="process_payment" class="btn-submit">
                <i class="fas fa-check me-2"></i>Process Payment
            </button>
        </form>

        <script>
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
                            Plan: ${sub.plan_name} | ₹${parseFloat(sub.amount_due).toFixed(2)} (Due: ${sub.end_date})
                        </option>`;
                    });
                    subscriptionSelect.innerHTML = options;

                    // Auto-select subscription and fill amount if specified
                    if (selectedSubscriptionId) {
                        const selectedOption = subscriptionSelect.querySelector(`option[value="${selectedSubscriptionId}"]`);
                        if (selectedOption) {
                            amountInput.value = selectedOption.dataset.amount || '';
                        }
                    }

                    // Update amount when subscription is selected
                    subscriptionSelect.onchange = function() {
                        const selected = this.options[this.selectedIndex];
                        amountInput.value = selected.dataset.amount || '';
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

<!-- Add Payment Modal -->
<div class="modal fade" id="addPaymentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-cash-register me-2"></i>Process New Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_payment">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Member ID *</label>
                            <input type="text" class="form-control" name="member_id" id="member_id" placeholder="Enter member ID" required onblur="checkUnpaidSubscriptions()">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Subscription ID (Optional)</label>
                            <input type="text" class="form-control" name="subscription_id" id="subscription_id" placeholder="Enter subscription ID">
                        </div>
                        <div class="col-12" id="unpaidSubscriptions" style="display: none;">
                            <label class="form-label">Unpaid Plans</label>
                            <div id="unpaidList" class="alert alert-info">
                                <!-- Unpaid plans will be loaded here -->
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Amount *</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" name="amount" step="0.01" min="0" placeholder="0.00" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment Method *</label>
                            <select class="form-select" name="payment_method" required>
                                <option value="">Select payment method</option>
                                <option value="Cash">Cash</option>
                                <option value="Card">Card</option>
                                <option value="Online">Online</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Check">Check</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description *</label>
                            <textarea class="form-control" name="description" rows="3" placeholder="Payment description (e.g., Monthly membership fee)" required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-credit-card me-2"></i>Process Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function checkUnpaidSubscriptions() {
    const memberId = document.getElementById('member_id').value;
    if (memberId) {
        fetch(`payments.php?action=get_unpaid_subscriptions&member_id=${memberId}`)
            .then(response => response.json())
            .then(data => {
                const unpaidDiv = document.getElementById('unpaidSubscriptions');
                const unpaidList = document.getElementById('unpaidList');
                if (data.plans && data.plans.length > 0) {
                    let html = `<strong>Unpaid Plans (Click to select):</strong><br><strong>Total Balance: $${data.total_balance}</strong><ul class="list-group">`;
                    data.plans.forEach(plan => {
                        html += `<li class="list-group-item list-group-item-action" onclick="selectPlan(${plan.plan_id}, ${plan.total_unpaid})" style="cursor: pointer;">${plan.plan_name} - $${plan.total_unpaid} (${plan.subscription_count} subscriptions)</li>`;
                    });
                    html += '</ul>';
                    unpaidList.innerHTML = html;
                    unpaidDiv.style.display = 'block';
                } else {
                    unpaidList.innerHTML = '<strong>No unpaid plans found.</strong>';
                    unpaidDiv.style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error fetching unpaid plans:', error);
            });
    } else {
        document.getElementById('unpaidSubscriptions').style.display = 'none';
    }
}

function selectPlan(planId, amount) {
    // Since we're selecting a plan, we might not set subscription_id directly
    // Instead, we can set the amount and perhaps leave subscription_id blank or set to a default
    document.getElementById('amount').value = amount;
    // Optionally hide the list after selection
    // document.getElementById('unpaidSubscriptions').style.display = 'none';
}

function selectSubscription(subId, amount) {
    document.getElementById('subscription_id').value = subId;
    document.getElementById('amount').value = amount;
    // Optionally hide the list after selection
    // document.getElementById('unpaidSubscriptions').style.display = 'none';
}

function processPendingPayment(subscriptionId, memberId, amount) {
    // Pre-fill the modal with pending payment details
    document.querySelector('input[name="member_id"]').value = memberId;
    document.querySelector('input[name="subscription_id"]').value = subscriptionId;
    document.querySelector('input[name="amount"]').value = amount;
    document.querySelector('textarea[name="description"]').value = 'Membership subscription payment';

    // Open the modal
    const modal = new bootstrap.Modal(document.getElementById('addPaymentModal'));
    modal.show();
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
