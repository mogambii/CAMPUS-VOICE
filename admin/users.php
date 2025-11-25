<?php
define('IN_CAMPUS_VOICE', true);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Ensure user is admin
requireAdmin();

// Get database connection
$db = getDB();

// Pagination
$per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Search
$search = $_GET['search'] ?? '';

// Build query
$query = "SELECT * FROM users WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR student_id LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}


// Get total count for pagination
$count_query = "SELECT COUNT(*) FROM ($query) as total";
$stmt = $db->prepare($count_query);
$stmt->execute($params);
$total_users = $stmt->fetchColumn();
$total_pages = ceil($total_users / $per_page);

// Add pagination to query
$query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;

// Execute query
$stmt = $db->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Define roles for filter
$roles = [
    ['id' => 'student', 'name' => 'Student'],
    ['id' => 'admin', 'name' => 'Admin']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Campus Voice Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Garamond:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #8b7355;
            --primary-hover: #7a6348;
            --bg-light: #f8f5f0;
            --border-color: #d4c9b8;
            --text-dark: #2c3e50;
            --text-muted: #6c757d;
        }
        
        body {
            font-family: 'Garamond', 'Georgia', 'Times New Roman', serif;
            background-color: var(--bg-light);
            color: var(--text-dark);
            line-height: 1.6;
        }

        .card {
            border: 1px solid var(--border-color);
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            background-color: #fff;
            margin-bottom: 2rem;
        }

        .card-header {
            background-color: #f0e9dd;
            border-bottom: 1px solid var(--border-color);
            padding: 1.25rem 1.5rem;
        }

        .table th {
            background-color: #f8f5f0;
            color: #5d4b36;
            font-weight: 600;
            border-bottom: 1px solid var(--border-color);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-hover);
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
            border-color: #69553d;
        }

        .form-control, .form-select {
            border: 1px solid var(--border-color);
            border-radius: 3px;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(139, 115, 85, 0.1);
        }

        .status-badge {
            padding: 0.35em 0.65em;
            border-radius: 3px;
            font-size: 0.85em;
            font-weight: 500;
        }

        .status-active {
            background-color: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background-color: #f8d7da;
            color: #721c24;
        }

        .pagination .page-link {
            color: var(--primary-color);
        }

        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-hover);
        }

        @media (max-width: 768px) {
            .table-responsive {
                overflow-x: auto;
            }
            
            .btn {
                padding: 0.4rem 0.8rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="mb-4">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>

        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">Manage Users</h4>
            </div>
            <div class="card-body">
                <!-- Search and Filter Form -->
                <form method="get" class="mb-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="Search by name, email, or student ID..." value="<?php echo htmlspecialchars($search); ?>">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-1"></i> Search
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Users Table -->
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                                        <p class="mb-0">No users found matching your criteria.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>#<?php echo $user['id']; ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="me-2">
                                                    <i class="fas fa-user-circle fa-lg text-muted"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-medium"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($user['student_id'] ?? 'N/A'); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?= ucfirst(htmlspecialchars($user['role'] ?? 'N/A')) ?></td>
                                        <td>
                                            <?php $isActive = $user['is_active'] ?? false; ?>
                                            <span class="status-badge <?php echo $isActive ? 'status-active' : 'status-inactive'; ?>">
                                                <i class="fas fa-<?php echo $isActive ? 'check-circle' : 'times-circle'; ?> me-1"></i>
                                                <?php echo $isActive ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-warning flag-user" 
                                                    data-id="<?php echo $user['id']; ?>"
                                                    title="Flag Inappropriate Content">
                                                <i class="fas fa-flag"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo; Previous</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" aria-label="Next">
                                        <span aria-hidden="true">Next &raquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle flag user action
        document.addEventListener('DOMContentLoaded', function() {
            const flagButtons = document.querySelectorAll('.flag-user');
            
            flagButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const userId = this.getAttribute('data-id');
                    const reason = prompt('Please specify the reason for flagging this user:', 'Inappropriate content');
                    
                    if (reason !== null) {
                        // Here you would typically make an AJAX call to flag the user
                        // For now, we'll just show an alert
                        alert(`User #${userId} has been flagged for: ${reason}`);
                        // You can uncomment the following code to submit the flag to the server
                        /*
                        const form = document.createElement('form');
                        form.method = 'post';
                        form.action = 'flag-user.php';
                        
                        const idInput = document.createElement('input');
                        idInput.type = 'hidden';
                        idInput.name = 'user_id';
                        idInput.value = userId;
                        
                        const reasonInput = document.createElement('input');
                        reasonInput.type = 'hidden';
                        reasonInput.name = 'reason';
                        reasonInput.value = reason;
                        
                        form.appendChild(idInput);
                        form.appendChild(reasonInput);
                        document.body.appendChild(form);
                        form.submit();
                        */
                    }
                });
            });
        });
    </script>
</body>
</html>
