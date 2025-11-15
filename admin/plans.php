<?php
// plans.php - Membership Plans Management
require_once '../config.php';
require_once '../session.php';
requireLogin();

$db = new Database();
$conn = $db->connect();

// Handle plan operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $stmt = $conn->prepare("INSERT INTO membership_plans (plan_name, description, duration_days, price, features, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['plan_name'],
                    $_POST['description'],
                    $_POST['duration_days'],
                    $_POST['price'],
                    $_POST['features'],
                    $_POST['status']
                ]);
                $success = "Plan added successfully!";
                break;
            case 'update':
                $stmt = $conn->prepare("UPDATE membership_plans SET plan_name=?, description=?, duration_days=?, price=?, features=?, status=? WHERE id=?");
                $stmt->execute([
                    $_POST['plan_name'],
                    $_POST['description'],
                    $_POST['duration_days'],
                    $_POST['price'],
                    $_POST['features'],
                    $_POST['status'],
                    $_POST['plan_id']
                ]);
                $success = "Plan updated successfully!";
                break;
            case 'delete':
                $stmt = $conn->prepare("DELETE FROM membership_plans WHERE id = ?");
                $stmt->execute([$_POST['plan_id']]);
                $success = "Plan deleted successfully!";
                break;
        }
    }
}

// Fetch all plans
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$query = "SELECT * FROM membership_plans WHERE 1=1";

if ($search) {
    $query .= " AND (plan_name LIKE '%$search%' OR description LIKE '%$search%' OR features LIKE '%$search%')";
}

if ($status_filter) {
    $query .= " AND status='$status_filter'";
}

$query .= " ORDER BY created_at DESC";

$stmt = $conn->query($query);
$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membership Plans - Gym Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Same styles as members.php */
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
            <a class="nav-link" href="payments.php">
                <i class="fas fa-dollar-sign me-2"></i> Payments
            </a>
            <a class="nav-link active" href="plans.php">
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
            <h2><i class="fas fa-list me-2"></i>Membership Plans</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPlanModal">
                <i class="fas fa-plus me-2"></i>Add New Plan
            </button>
        </div>

        <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="search" placeholder="Search by plan name, description, or features" value="<?php echo $search; ?>">
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
                        <a href="plans.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-redo me-2"></i>Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Plans Table -->
        <div class="card">
            <div class="card-header"><i class="fas fa-list me-2"></i>All Plans (<?php echo count($plans); ?>)</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Plan Name</th>
                                <th>Description</th>
                                <th>Duration (Days)</th>
                                <th>Price</th>
                                <th>Features</th>
                                <th>Status</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($plans as $plan): ?>
                            <tr>
                                <td><?php echo $plan['plan_name']; ?></td>
                                <td><?php echo $plan['description']; ?></td>
                                <td><?php echo $plan['duration_days']; ?></td>
                                <td>₱<?php echo $plan['price']; ?></td>
                                <td><?php echo $plan['features']; ?></td>
                                <td>
                                    <span class="badge <?php echo $plan['status']=='Active'?'bg-success':'bg-secondary'; ?>">
                                        <?php echo $plan['status']; ?>
                                    </span>
                                </td>
                                <td><?php echo $plan['created_at']; ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-warning" onclick="editPlan(<?php echo $plan['id']; ?>)"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deletePlan(<?php echo $plan['id']; ?>)"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($plans)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">No plans found</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Plan Modal -->
    <div class="modal fade" id="addPlanModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add/Edit Plan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="plan_id" id="plan_id">
                    <input type="hidden" name="action" id="plan_action" value="add">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Plan Name *</label>
                                <input type="text" class="form-control" name="plan_name" id="plan_name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Duration (Days) *</label>
                                <input type="number" class="form-control" name="duration_days" id="duration_days" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Price *</label>
                                <input type="number" step="0.01" class="form-control" name="price" id="price" required>
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
                                <textarea class="form-control" name="description" id="description" rows="2"></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Features</label>
                                <textarea class="form-control" name="features" id="features" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save Plan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deletePlan(id) {
            if (confirm('Are you sure you want to delete this plan?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="plan_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function editPlan(id) {
            const row = document.querySelector(`button[onclick='editPlan(${id})']`).closest('tr');
            document.getElementById('plan_id').value = id;
            document.getElementById('plan_name').value = row.cells[0].innerText;
            document.getElementById('description').value = row.cells[1].innerText;
            document.getElementById('duration_days').value = row.cells[2].innerText;
            document.getElementById('price').value = row.cells[3].innerText.replace('$','');
            document.getElementById('features').value = row.cells[4].innerText;
            document.getElementById('status').value = row.cells[5].innerText.trim();
            document.getElementById('plan_action').value = 'update';
            var addPlanModal = new bootstrap.Modal(document.getElementById('addPlanModal'));
            addPlanModal.show();
        }
    </script>
</body>
</html>
