<?php
// payments.php - Payments POS
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
<title>Payments POS - Gym Management</title>
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
