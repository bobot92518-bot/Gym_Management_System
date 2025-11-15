<?php
// discounts.php - Discounts Management
require_once '../config.php';
require_once '../session.php';
requireLogin();

$db = new Database();
$conn = $db->connect();

$message = '';
$messageType = '';

// Handle discount operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'add':
                    $discount_name = filter_input(INPUT_POST, 'discount_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                    $discount_type = filter_input(INPUT_POST, 'discount_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                    $discount_value = filter_input(INPUT_POST, 'discount_value', FILTER_VALIDATE_FLOAT);
                    $min_amount = filter_input(INPUT_POST, 'min_amount', FILTER_VALIDATE_FLOAT);
                    $max_discount = filter_input(INPUT_POST, 'max_discount', FILTER_VALIDATE_FLOAT);
                    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

                    if (!$discount_name || !$discount_value) {
                        throw new Exception("Discount name and value are required");
                    }

                    $stmt = $conn->prepare("INSERT INTO discounts (discount_name, description, discount_type, discount_value, min_amount, max_discount, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $discount_name,
                        $description,
                        $discount_type,
                        $discount_value,
                        $min_amount,
                        $max_discount,
                        $status
                    ]);
                    $message = "Discount added successfully!";
                    $messageType = "success";
                    break;

                case 'update':
                    $discount_id = intval($_POST['discount_id']);
                    $discount_name = filter_input(INPUT_POST, 'discount_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                    $discount_type = filter_input(INPUT_POST, 'discount_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                    $discount_value = filter_input(INPUT_POST, 'discount_value', FILTER_VALIDATE_FLOAT);
                    $min_amount = filter_input(INPUT_POST, 'min_amount', FILTER_VALIDATE_FLOAT);
                    $max_discount = filter_input(INPUT_POST, 'max_discount', FILTER_VALIDATE_FLOAT);
                    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

                    if (!$discount_name || !$discount_value) {
                        throw new Exception("Discount name and value are required");
                    }

                    $stmt = $conn->prepare("UPDATE discounts SET discount_name=?, description=?, discount_type=?, discount_value=?, min_amount=?, max_discount=?, status=? WHERE id=?");
                    $stmt->execute([
                        $discount_name,
                        $description,
                        $discount_type,
                        $discount_value,
                        $min_amount,
                        $max_discount,
                        $status,
                        $discount_id
                    ]);
                    $message = "Discount updated successfully!";
                    $messageType = "success";
                    break;

                case 'delete':
                    $discount_id = intval($_POST['discount_id']);
                    $stmt = $conn->prepare("DELETE FROM discounts WHERE id = ?");
                    $stmt->execute([$discount_id]);
                    $message = "Discount deleted successfully!";
                    $messageType = "success";
                    break;
            }
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Fetch all discounts
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$query = "SELECT * FROM discounts WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (discount_name LIKE ? OR description LIKE ?)";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm];
}

if ($status_filter) {
    $query .= " AND status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$discounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discounts - Gym Management</title>
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
            display: block;
            text-decoration: none;
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
        .discount-type-badge {
            font-size: 11px;
            padding: 4px 8px;
            border-radius: 4px;
        }
        .discount-type-percentage {
            background: #c3dafe;
            color: #1a365d;
        }
        .discount-type-fixed {
            background: #d1fae5;
            color: #065f46;
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
            <a class="nav-link" href="payments.php">
                <i class="fas fa-dollar-sign me-2"></i> Payments
            </a>
            <a class="nav-link" href="plans.php">
                <i class="fas fa-list me-2"></i> Membership Plans
            </a>
            <a class="nav-link active" href="discounts.php">
                <i class="fas fa-tag me-2"></i> Discounts
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
            <h2><i class="fas fa-tag me-2"></i>Discounts Management</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDiscountModal">
                <i class="fas fa-plus me-2"></i>Add New Discount
            </button>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
            <i class="fas fa-<?php echo ($messageType === 'success' ? 'check-circle' : 'exclamation-circle'); ?> me-2"></i><?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="search" placeholder="Search by discount name..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="Active" <?php echo $status_filter=='Active'?'selected':''; ?>>Active</option>
                            <option value="Inactive" <?php echo $status_filter=='Inactive'?'selected':''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i>Search
                        </button>
                    </div>
                    <div class="col-md-2">
                        <a href="discounts.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-redo me-2"></i>Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Discounts Table -->
        <div class="card">
            <div class="card-header"><i class="fas fa-tag me-2"></i>All Discounts (<?php echo count($discounts); ?>)</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Discount Name</th>
                                <th>Type</th>
                                <th>Value</th>
                                <th>Min Amount</th>
                                <th>Max Discount</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($discounts as $discount): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($discount['discount_name']); ?></strong></td>
                                <td>
                                    <span class="discount-type-badge <?php echo $discount['discount_type'] === 'Percentage' ? 'discount-type-percentage' : 'discount-type-fixed'; ?>">
                                        <?php echo $discount['discount_type'] === 'Percentage' ? '%' : '₱'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $discount['discount_type'] === 'Percentage' ? $discount['discount_value'] . '%' : '₱' . number_format($discount['discount_value'], 2); ?>
                                </td>
                                <td><?php echo $discount['min_amount'] ? '₱' . number_format($discount['min_amount'], 2) : '-'; ?></td>
                                <td><?php echo $discount['max_discount'] ? '₱' . number_format($discount['max_discount'], 2) : '-'; ?></td>
                                <td><?php echo htmlspecialchars(substr($discount['description'] ?? '', 0, 50)) . (strlen($discount['description'] ?? '') > 50 ? '...' : ''); ?></td>
                                <td>
                                    <span class="badge <?php echo $discount['status']=='Active'?'bg-success':'bg-secondary'; ?>">
                                        <?php echo $discount['status']; ?>
                                    </span>
                                </td>
                                <td><?php echo date('d-M-Y', strtotime($discount['created_at'])); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-warning" onclick="editDiscount(<?php echo $discount['id']; ?>)"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteDiscount(<?php echo $discount['id']; ?>)"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($discounts)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted">No discounts found</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Discount Modal -->
    <div class="modal fade" id="addDiscountModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add/Edit Discount</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="discount_id" id="discount_id">
                    <input type="hidden" name="action" id="discount_action" value="add">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Discount Name *</label>
                                <input type="text" class="form-control" name="discount_name" id="discount_name" placeholder="e.g., New Year Sale" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Type *</label>
                                <select class="form-select" name="discount_type" id="discount_type" required onchange="updateDiscountValue()">
                                    <option value="Percentage">Percentage (%)</option>
                                    <option value="Fixed Amount">Fixed Amount (₱)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Discount Value *</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" class="form-control" name="discount_value" id="discount_value" placeholder="0.00" required>
                                    <span class="input-group-text" id="value-unit">%</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Minimum Amount (Optional)</label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" step="0.01" class="form-control" name="min_amount" id="min_amount" placeholder="0.00">
                                </div>
                                <small class="text-muted">Discount applies only for amounts above this value</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Maximum Discount Cap (Optional)</label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" step="0.01" class="form-control" name="max_discount" id="max_discount" placeholder="0.00">
                                </div>
                                <small class="text-muted">Maximum discount amount that can be applied</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status *</label>
                                <select class="form-select" name="status" id="status" required>
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" id="description" rows="2" placeholder="Enter discount description..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save Discount</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateDiscountValue() {
            const type = document.getElementById('discount_type').value;
            const unit = document.getElementById('value-unit');
            unit.textContent = type === 'Percentage' ? '%' : '₱';
        }

        function deleteDiscount(id) {
            if (confirm('Are you sure you want to delete this discount?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="discount_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function editDiscount(id) {
            const row = document.querySelector(`button[onclick='editDiscount(${id})']`).closest('tr');
            const cells = row.cells;

            document.getElementById('discount_id').value = id;
            document.getElementById('discount_name').value = cells[0].innerText.trim();
            
            // Determine type from the badge
            const typeText = cells[1].innerText.trim();
            const discount_type = typeText === '%' ? 'Percentage' : 'Fixed Amount';
            document.getElementById('discount_type').value = discount_type;
            updateDiscountValue();

            // Parse the value
            const valueText = cells[2].innerText.trim();
            const value = parseFloat(valueText.replace(/[₱%]/g, ''));
            document.getElementById('discount_value').value = value;

            // Min amount
            const minText = cells[3].innerText.trim();
            document.getElementById('min_amount').value = minText === '-' ? '' : parseFloat(minText.replace('₱', ''));

            // Max discount
            const maxText = cells[4].innerText.trim();
            document.getElementById('max_discount').value = maxText === '-' ? '' : parseFloat(maxText.replace('₱', ''));

            // Description
            document.getElementById('description').value = cells[5].innerText.trim();

            // Status
            const statusText = cells[6].innerText.trim();
            document.getElementById('status').value = statusText;

            document.getElementById('discount_action').value = 'update';
            var addDiscountModal = new bootstrap.Modal(document.getElementById('addDiscountModal'));
            addDiscountModal.show();
        }
    </script>
</body>
</html>
