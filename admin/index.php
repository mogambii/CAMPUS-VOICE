<?php
// Define constant to prevent direct access
define('IN_CAMPUS_VOICE', true);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Check if user is logged in as admin
requireAdmin();

$db = getDB();
$stats = [
    'total_users' => 0,
    'total_feedback' => 0,
    'pending_feedback' => 0,
    'resolved_feedback' => 0,
    'total_polls' => 0,
    'active_polls' => 0
];

// Get statistics
try {
    // User statistics
    $stmt = $db->query("SELECT COUNT(*) as count FROM users");
    $stats['total_users'] = $stmt->fetch()['count'];
    
    // Feedback statistics
    $stmt = $db->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved
        FROM feedback");
    $feedbackStats = $stmt->fetch();
    $stats['total_feedback'] = $feedbackStats['total'];
    $stats['pending_feedback'] = $feedbackStats['pending'];
    $stats['resolved_feedback'] = $feedbackStats['resolved'];
    
    // Poll statistics
    $stmt = $db->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN end_date > NOW() OR end_date IS NULL THEN 1 ELSE 0 END) as active
        FROM polls");
    $pollStats = $stmt->fetch();
    $stats['total_polls'] = $pollStats['total'];
    $stats['active_polls'] = $pollStats['active'];
    
} catch (Exception $e) {
    error_log('Error fetching admin statistics: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Campus Voice</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-dashboard">
    <div class="d-flex">
        <!-- Sidebar -->
        <?php include 'includes/admin_sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex-grow-1">
            <!-- Top Navigation -->
            <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: var(--primary-color) !important; border-bottom: 1px solid var(--primary-dark);">
                <div class="container-fluid">
                    <button class="btn btn-link text-white me-3 d-lg-none" type="button" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <span class="navbar-brand">Admin Dashboard</span>
                    <div class="d-flex align-items-center ms-auto">
                        <div class="dropdown">
                            <a href="#" class="nav-link dropdown-toggle text-white" id="userDropdown" role="button" 
                               data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle me-1"></i>
                                <?php echo htmlspecialchars($_SESSION['first_name'] ?? 'Admin'); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li><a class="dropdown-item" href="../profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Main Content -->
            <div class="container-fluid p-4">
                <h2 class="mb-4">Dashboard Overview</h2>
                
                <!-- Stats Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6 col-xl-3">
                        <div class="card" style="background-color: var(--primary-color); color: var(--light-color);">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase">Total Users</h6>
                                        <h2 class="mb-0"><?php echo $stats['total_users']; ?></h2>
                                    </div>
                                    <div class="icon-circle">
                                        <i class="fas fa-users"></i>
                                    </div>
                                </div>
                                <a href="users.php" class="stretched-link text-white text-decoration-none"></a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-xl-3">
                        <div class="card" style="background-color: var(--success-color); color: #fff;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase">Total Feedback</h6>
                                        <h2 class="mb-0"><?php echo $stats['total_feedback']; ?></h2>
                                        <small><?php echo $stats['resolved_feedback']; ?> resolved</small>
                                    </div>
                                    <div class="icon-circle">
                                        <i class="fas fa-comment-dots"></i>
                                    </div>
                                </div>
                                <a href="manage-feedback.php" class="stretched-link text-white text-decoration-none"></a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-xl-3">
                        <div class="card" style="background-color: var(--warning-color); color: #fff;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase">Pending Feedback</h6>
                                        <h2 class="mb-0"><?php echo $stats['pending_feedback']; ?></h2>
                                    </div>
                                    <div class="icon-circle">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                </div>
                                <a href="manage-feedback.php?status=pending" class="stretched-link text-white text-decoration-none"></a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-xl-3">
                        <div class="card" style="background-color: var(--secondary-color); color: #fff;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase">Active Polls</h6>
                                        <h2 class="mb-0"><?php echo $stats['active_polls']; ?></h2>
                                        <small>of <?php echo $stats['total_polls']; ?> total</small>
                                    </div>
                                    <div class="icon-circle">
                                        <i class="fas fa-poll"></i>
                                    </div>
                                </div>
                                <a href="polls.php" class="stretched-link text-white text-decoration-none"></a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-header" style="background-color: #fff; border-bottom: 1px solid #e0e0e0;">
                                <h5 class="mb-0">Recent Feedback</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead style="background-color: var(--light-color);">
                                            <tr>
                                                <th>ID</th>
                                                <th>Title</th>
                                                <th>Category</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            try {
                                                $stmt = $db->query("
                                                    SELECT f.*, c.name as category_name 
                                                    FROM feedback f 
                                                    LEFT JOIN categories c ON f.category_id = c.id 
                                                    ORDER BY f.created_at DESC 
                                                    LIMIT 5
                                                ")->fetchAll();
                                                
                                                foreach ($stmt as $row):
                                                    $statusClass = [
                                                        'pending' => 'warning',
                                                        'in_progress' => 'info',
                                                        'resolved' => 'success',
                                                        'rejected' => 'danger'
                                                    ][$row['status']] ?? 'secondary';
                                            ?>
                                            <tr>
                                                <td>#<?php echo $row['id']; ?></td>
                                                <td><?php echo htmlspecialchars($row['title']); ?></td>
                                                <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                                                <td>
                                                    <span class="badge" style="background-color: var(--<?php echo $statusClass; ?>-color); color: #fff;">
                                                        <?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M j, Y', strtotime($row['created_at'])); ?></td>
                                                <td>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-outline-warning flag-feedback" 
                                                            data-id="<?php echo $row['id']; ?>"
                                                            title="Flag as Inappropriate">
                                                        <i class="fas fa-flag"></i> Flag
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php } catch (Exception $e) { 
                                                error_log('Error fetching recent feedback: ' . $e->getMessage());
                                            } ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="card-footer" style="background-color: #fff; border-top: 1px solid #e0e0e0; text-align: right;">
                                    <a href="manage-feedback.php" class="btn btn-sm btn-outline-primary">
                                        View All Feedback <i class="fas fa-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <!-- Quick Stats -->
                        <div class="card mb-4">
                            <div class="card-header" style="background-color: #fff; border-bottom: 1px solid #e0e0e0;">
                                <h5 class="mb-0">Quick Actions</h5>
                            </div>
                            <div class="list-group list-group-flush">
                                <a href="new-poll.php" class="list-group-item list-group-item-action">
                                    <i class="fas fa-poll me-2" style="color: var(--success-color);"></i> Create New Poll
                                </a>
<a href="users.php" class="list-group-item list-group-item-action">
                                    <i class="fas fa-users-cog me-2" style="color: var(--warning-color);"></i> Manage Users
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Handle flag feedback
        document.querySelectorAll('.flag-feedback').forEach(button => {
            button.addEventListener('click', function() {
                const feedbackId = this.getAttribute('data-id');
                
                Swal.fire({
                    title: 'Flag as Inappropriate',
                    text: 'Are you sure you want to flag this feedback as inappropriate?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ffc107',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, flag it!',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Send AJAX request to flag the feedback
                        fetch('flag-feedback.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'id=' + feedbackId
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire(
                                    'Flagged!',
                                    'The feedback has been flagged as inappropriate.',
                                    'success'
                                ).then(() => {
                                    // Optionally refresh the page or update the UI
                                    location.reload();
                                });
                            } else {
                                throw new Error(data.message || 'Failed to flag feedback');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            Swal.fire(
                                'Error!',
                                'There was an error flagging the feedback: ' + error.message,
                                'error'
                            );
                        });
                    }
                });
            });
        });

        // Toggle sidebar on mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.admin-dashboard').classList.toggle('sidebar-toggled');
        });
    </script>
</body>
</html>
