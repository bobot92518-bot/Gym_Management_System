<?php
// members.php - Member Management
require_once 'config.php';
require_once 'session.php';
requireLogin();

$db = new Database();
$conn = $db->connect();

// Handle member operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $member_id = 'MEM' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $stmt = $conn->prepare("INSERT INTO members (member_id, first_name, last_name, email, phone, address, date_of_birth, gender, emergency_contact, emergency_phone, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')");
                $stmt->execute([
                    $member_id,
                    $_POST['first_name'],
                    $_POST['last_name'],
                    $_POST['email'],
                    $_POST['phone'],
                    $_POST['address'],
                    $_POST['dob'],
                    $_POST['gender'],
                    $_POST['emergency_contact'],
                    $_POST['emergency_phone']
                ]);
                $success = "Member added successfully!";
                break;
                
            case 'update':
                $stmt = $conn->prepare("UPDATE members SET first_name=?, last_name=?, email=?, phone=?, address=?, date_of_birth=?, gender=?, emergency_contact=?, emergency_phone=?, status=? WHERE id=?");
                $stmt->execute([
                    $_POST['first_name'],
                    $_POST['last_name'],
                    $_POST['email'],
                    $_POST['phone'],
                    $_POST['address'],
                    $_POST['dob'],
                    $_POST['gender'],
                    $_POST['emergency_contact'],
                    $_POST['emergency_phone'],
                    $_POST['status'],
                    $_POST['member_id']
                ]);
                $success = "Member updated successfully!";
                break;
                
            case 'delete':
                $stmt = $conn->prepare("DELETE FROM members WHERE id = ?");
                $stmt->execute([$_POST['member_id']]);
                $success = "Member deleted successfully!";
                break;
        }
    }
}

// Search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$query = "SELECT m.*, 
          (SELECT COUNT(*) FROM attendance a WHERE a.member_id = m.id AND a.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as monthly_visits,
          (SELECT end_date FROM subscriptions s WHERE s.member_id = m.id AND s.status = 'Active' ORDER BY end_date DESC LIMIT 1) as subscription_end
          FROM members m WHERE 1=1";

if ($search) {
    $query .= " AND (m.member_id LIKE '%$search%' OR m.first_name LIKE '%$search%' OR m.last_name LIKE '%$search%' OR m.email LIKE '%$search%' OR m.phone LIKE '%$search%')";
}

if ($status_filter) {
    $query .= " AND m.status = '$status_filter'";
}

$query .= " ORDER BY m.created_at DESC";

$stmt = $conn->query($query);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Members - Gym Management System</title>
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
        
        .member-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .badge {
            padding: 5px 10px;
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
            <a class="nav-link active" href="members.php">
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
            <a class="nav-link" href="reports.php">
                <i class="fas fa-chart-bar me-2"></i> Reports
            </a>
            <a class="nav-link" href="member_dashboard.php">
                <i class="fas fa-user-circle me-2"></i> Member Dashboard
            </a>
            <?php if (isAdmin()): ?>
            <a class="nav-link" href="admin_reset.php">
                <i class="fas fa-key me-2"></i> Password Reset
            </a>
            <?php endif; ?>
            <hr style="border-color: rgba(255,255,255,0.2);">
            <a class="nav-link" href="logout.php">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-users me-2"></i>Member Management</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMemberModal">
                <i class="fas fa-plus me-2"></i>Add New Member
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
                        <input type="text" class="form-control" name="search" placeholder="Search by name, ID, email, or phone" value="<?php echo $search; ?>">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="Active" <?php echo $status_filter == 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo $status_filter == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="Suspended" <?php echo $status_filter == 'Suspended' ? 'selected' : ''; ?>>Suspended</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i>Search
                        </button>
                    </div>
                    <div class="col-md-2">
                        <a href="members.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-redo me-2"></i>Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Members Table -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list me-2"></i>All Members (<?php echo count($members); ?>)
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Member ID</th>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Subscription</th>
                                <th>Monthly Visits</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($members as $member): ?>
                            <tr>
                                <td><strong><?php echo $member['member_id']; ?></strong></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="member-avatar bg-primary text-white d-flex align-items-center justify-content-center me-2">
                                            <?php echo strtoupper(substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <?php echo $member['first_name'] . ' ' . $member['last_name']; ?><br>
                                            <small class="text-muted"><?php echo $member['gender']; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php echo $member['email']; ?><br>
                                    <small><?php echo $member['phone']; ?></small>
                                </td>
                                <td>
                                    <?php if ($member['subscription_end']): ?>
                                        <?php 
                                        $end_date = strtotime($member['subscription_end']);
                                        $today = strtotime(date('Y-m-d'));
                                        $days_left = ($end_date - $today) / 86400;
                                        ?>
                                        <?php if ($days_left > 7): ?>
                                            <span class="badge bg-success">Active</span><br>
                                            <small>Until <?php echo date('M d, Y', $end_date); ?></small>
                                        <?php elseif ($days_left > 0): ?>
                                            <span class="badge bg-warning">Expiring Soon</span><br>
                                            <small><?php echo ceil($days_left); ?> days left</small>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Expired</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">No Subscription</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $member['monthly_visits']; ?> visits</span>
                                </td>
                                <td>
                                    <?php
                                    $status_class = '';
                                    switch($member['status']) {
                                        case 'Active': $status_class = 'bg-success'; break;
                                        case 'Inactive': $status_class = 'bg-secondary'; break;
                                        case 'Suspended': $status_class = 'bg-danger'; break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $status_class; ?>"><?php echo $member['status']; ?></span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewMember(<?php echo $member['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-warning" onclick="editMember(<?php echo $member['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteMember(<?php echo $member['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($members)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">No members found</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Member Modal -->
    <div class="modal fade" id="addMemberModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Add New Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">First Name *</label>
                                <input type="text" class="form-control" name="first_name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last Name *</label>
                                <input type="text" class="form-control" name="last_name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone *</label>
                                <input type="text" class="form-control" name="phone" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" name="dob">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Gender</label>
                                <select class="form-select" name="gender">
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" name="address" rows="2"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Emergency Contact Name</label>
                                <input type="text" class="form-control" name="emergency_contact">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Emergency Contact Phone</label>
                                <input type="text" class="form-control" name="emergency_phone">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Member
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteMember(id) {
            if (confirm('Are you sure you want to delete this member?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="member_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>