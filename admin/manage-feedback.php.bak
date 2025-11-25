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
$status = $_GET['status'] ?? 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $feedback_id = (int)$_POST['feedback_id'];
    $new_status = $_POST['status'];
    $admin_notes = $_POST['admin_notes'] ?? '';
    
    $update = $db->prepare("UPDATE feedback SET status = ?, admin_notes = ?, updated_at = NOW() WHERE id = ?");
    if ($update->execute([$new_status, $admin_notes, $feedback_id])) {
        $_SESSION['success'] = "Feedback status updated successfully!";
        header("Location: " . $_SERVER['PHP_SELF'] . "?status=$status&page=$page");
        exit;
    } else {
        $error = "Failed to update feedback status.";
    }
}

// Handle response submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_response'])) {
    $feedback_id = (int)$_POST['feedback_id'];
    $response = trim($_POST['response']);
    
    if (!empty($response)) {
        $insert = $db->prepare("INSERT INTO feedback_responses (feedback_id, admin_id, response, created_at) 
                               VALUES (?, ?, ?, NOW())");
        if ($insert->execute([$feedback_id, $_SESSION['admin_id'], $response])) {
            // Update feedback's updated_at timestamp
            $db->prepare("UPDATE feedback SET updated_at = NOW() WHERE id = ?")->execute([$feedback_id]);
            
            // Get user ID to send notification
            $stmt = $db->prepare("SELECT user_id, title FROM feedback WHERE id = ?");
            $stmt->execute([$feedback_id]);
            $feedback = $stmt->fetch();
            
            if ($feedback) {
                // Send notification to the user
                $notification = $db->prepare("INSERT INTO notifications 
                    (user_id, title, message, type, reference_id, created_at) 
                    VALUES (?, ?, ?, 'feedback_response', ?, NOW())");
                $notification->execute([
                    $feedback['user_id'],
                    'New Response to Your Feedback',
                    'You have received a new response to your feedback: ' . $feedback['title'],
                    $feedback_id
                ]);
            }
            
            $_SESSION['success'] = "Response added successfully!";
            header("Location: " . $_SERVER['PHP_SELF'] . "?status=$status&page=$page#feedback-$feedback_id");
            exit;
        } else {
            $error = "Failed to add response.";
        }
    }
}

// Build where clause for status filter
$where_clause = "WHERE 1=1";
if (in_array($status, ['pending', 'in_progress', 'resolved', 'rejected'])) {
    $where_clause .= " AND f.status = :status";
}

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM feedback f $where_clause";
$stmt = $db->prepare($count_query);
if ($status !== 'all') {
    $stmt->bindValue(':status', $status);
}
$stmt->execute();
$total_feedback = $stmt->fetch()['total'];
$total_pages = ceil($total_feedback / $per_page);

// Get feedback with user details and response count
$query = "SELECT 
    f.*, 
    u.first_name, 
    u.last_name, 
    u.email,
    c.name as category_name,
    (SELECT COUNT(*) FROM feedback_responses WHERE feedback_id = f.id) as response_count
    FROM feedback f
    LEFT JOIN users u ON f.user_id = u.id
    LEFT JOIN categories c ON f.category_id = c.id
    $where_clause
    ORDER BY f.created_at DESC
    LIMIT :offset, :per_page";

$stmt = $db->prepare($query);
if ($status !== 'all') {
    $stmt->bindValue(':status', $status);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
$stmt->execute();
$feedbacks = $stmt->fetchAll();

// Function to get responses for a feedback
function getFeedbackResponses($feedback_id) {
    global $db;
    try {
        // First, check if the feedback_responses table exists
        $tableExists = $db->query("SHOW TABLES LIKE 'feedback_responses'")->rowCount() > 0;
        
        if (!$tableExists) {
            return []; // Return empty array if table doesn't exist
        }
        
        $stmt = $db->prepare("SELECT fr.*, a.username as admin_name 
                             FROM feedback_responses fr 
                             LEFT JOIN admins a ON fr.admin_id = a.id 
                             WHERE fr.feedback_id = ? 
                             ORDER BY fr.created_at ASC");
        $stmt->execute([$feedback_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error in getFeedbackResponses: " . $e->getMessage());
        return []; // Return empty array on error
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Feedback - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .status-badge {
            font-size: 0.8rem;
            padding: 0.35em 0.65em;
        }
        .feedback-card {
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
            border-left: 4px solid #dee2e6;
        }
        .feedback-card.pending { border-left-color: #ffc107; }
        .feedback-card.in_progress { border-left-color: #0dcaf0; }
        .feedback-card.resolved { border-left-color: #198754; }
        .feedback-card.rejected { border-left-color: #dc3545; }
        
        .feedback-card:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        .response-form {
            display: none;
            margin-top: 1rem;
        }
        .response {
            background-color: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 3px solid #dee2e6;
        }
        .admin-response {
            background-color: #e9ecef;
            margin-left: 2rem;
            border-left-color: #0d6efd;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        .status-select {
            cursor: pointer;
            transition: all 0.2s;
        }
        .status-select:hover {
            transform: scale(1.05);
        }
        .pagination .page-link {
            color: #0d6efd;
        }
        .pagination .page-item.active .page-link {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
    </style>
</head>
<body class="admin-dashboard">
    <div class="d-flex">
        <?php include 'includes/admin_sidebar.php'; ?>
        
        <div class="flex-grow-1">
            <!-- Top Navigation -->
            <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
                <div class="container-fluid">
                    <button class="btn btn-link text-white me-3 d-lg-none" type="button" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <span class="navbar-brand">Manage Feedback</span>
                    <div class="d-flex align-items-center ms-auto">
                        <a href="index.php" class="btn btn-outline-light btn-sm me-2">
                            <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </nav>

            <!-- Main Content -->
            <div class="container-fluid p-4">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['success']; 
                        unset($_SESSION['success']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <!-- Status Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                            <h5 class="card-title mb-3 mb-md-0">Filter Feedback</h5>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="?status=all" class="btn btn-sm btn-outline-primary <?= $status === 'all' ? 'active' : '' ?>">
                                    <i class="fas fa-list me-1"></i> All
                                    <span class="badge bg-primary ms-1"><?= $total_feedback ?></span>
                                </a>
                                <a href="?status=pending" class="btn btn-sm btn-outline-warning <?= $status === 'pending' ? 'active' : '' ?>">
                                    <i class="fas fa-clock me-1"></i> Pending
                                </a>
                                <a href="?status=in_progress" class="btn btn-sm btn-outline-info <?= $status === 'in_progress' ? 'active' : '' ?>">
                                    <i class="fas fa-spinner me-1"></i> In Progress
                                </a>
                                <a href="?status=resolved" class="btn btn-sm btn-outline-success <?= $status === 'resolved' ? 'active' : '' ?>">
                                    <i class="fas fa-check-circle me-1"></i> Resolved
                                </a>
                                <a href="?status=rejected" class="btn btn-sm btn-outline-danger <?= $status === 'rejected' ? 'active' : '' ?>">
                                    <i class="fas fa-times-circle me-1"></i> Rejected
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Feedback List -->
                <?php if (empty($feedbacks)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> No feedback found.
                    </div>
                <?php else: ?>
                    <?php foreach ($feedbacks as $feedback): 
                        $responses = getFeedbackResponses($feedback['id']);
                        $status_class = [
                            'pending' => 'warning',
                            'in_progress' => 'info',
                            'resolved' => 'success',
                            'rejected' => 'danger'
                        ][$feedback['status']] ?? 'secondary';
                    ?>
                    <div class="card mb-4 feedback-card <?= $feedback['status'] ?>" id="feedback-<?= $feedback['id'] ?>">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <?php if (!empty($feedback['profile_picture'])): ?>
                                    <img src="../uploads/profiles/<?= htmlspecialchars($feedback['profile_picture']) ?>" 
                                         alt="User" class="user-avatar me-2">
                                <?php else: ?>
                                    <div class="user-avatar bg-secondary d-flex align-items-center justify-content-center text-white me-2">
                                        <i class="fas fa-user"></i>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <h6 class="mb-0"><?= htmlspecialchars($feedback['first_name'] . ' ' . $feedback['last_name']) ?></h6>
                                    <small class="text-muted"><?= htmlspecialchars($feedback['email']) ?></small>
                                </div>
                            </div>
                            <div class="d-flex align-items-center">
                                <span class="badge bg-<?= $status_class ?> me-2">
                                    <?= ucfirst(str_replace('_', ' ', $feedback['status'])) ?>
                                </span>
                                <small class="text-muted">
                                    <?= date('M j, Y \a\t g:i A', strtotime($feedback['created_at'])) ?>
                                </small>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h5 class="card-title mb-0"><?= htmlspecialchars($feedback['title']) ?></h5>
                                <?php if (!empty($feedback['category_name'])): ?>
                                    <span class="badge bg-light text-dark">
                                        <i class="fas fa-tag me-1"></i> <?= htmlspecialchars($feedback['category_name']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <p class="card-text"><?= nl2br(htmlspecialchars($feedback['description'])) ?></p>
                            
                            <?php if (!empty($feedback['image_path'])): ?>
                                <div class="mb-3">
                                    <img src="../uploads/feedback/<?= htmlspecialchars($feedback['image_path']) ?>" 
                                         alt="Feedback Image" class="img-fluid rounded" style="max-height: 200px;">
                                </div>
                            <?php endif; ?>
                            
                            <!-- Status Update Form -->
                            <form method="POST" class="mb-3">
                                <input type="hidden" name="feedback_id" value="<?= $feedback['id'] ?>">
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <select name="status" class="form-select status-select" onchange="this.form.submit()">
                                            <option value="pending" <?= $feedback['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="in_progress" <?= $feedback['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                            <option value="resolved" <?= $feedback['status'] === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                                            <option value="rejected" <?= $feedback['status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                        </select>
                                        <input type="hidden" name="update_status" value="1">
                                    </div>
                                    <div class="col-md-8">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-edit"></i></span>
                                            <input type="text" name="admin_notes" class="form-control" 
                                                   placeholder="Add internal notes (optional)" 
                                                   value="<?= htmlspecialchars($feedback['admin_notes'] ?? '') ?>">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save"></i> Save
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                            
                            <!-- Responses Section -->
                            <div class="mt-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0">
                                        <i class="fas fa-comments me-2"></i>
                                        Responses 
                                        <span class="badge bg-primary rounded-pill"><?= count($responses) ?></span>
                                    </h6>
                                    <button class="btn btn-sm btn-outline-primary" 
                                            onclick="document.getElementById('response-form-<?= $feedback['id'] ?>').style.display='block';
                                                     document.getElementById('response-text-<?= $feedback['id'] ?>').focus();">
                                        <i class="fas fa-reply me-1"></i> Add Response
                                    </button>
                                </div>
                                
                                <!-- Response Form -->
                                <div id="response-form-<?= $feedback['id'] ?>" class="response-form mb-3">
                                    <form method="POST">
                                        <input type="hidden" name="feedback_id" value="<?= $feedback['id'] ?>">
                                        <div class="mb-2">
                                            <textarea name="response" id="response-text-<?= $feedback['id'] ?>" 
                                                      class="form-control" rows="3" 
                                                      placeholder="Type your response here..." required></textarea>
                                        </div>
                                        <div class="d-flex justify-content-end gap-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                    onclick="this.closest('.response-form').style.display='none';">
                                                Cancel
                                            </button>
                                            <button type="submit" name="add_response" class="btn btn-sm btn-primary">
                                                <i class="fas fa-paper-plane me-1"></i> Send Response
                                            </button>
                                        </div>
                                    </form>
                                </div>
                                
                                <!-- Responses List -->
                                <div class="responses mt-3">
                                    <?php if (empty($responses)): ?>
                                        <div class="text-center py-3 text-muted">
                                            <i class="fas fa-comment-slash fa-2x mb-2"></i>
                                            <p class="mb-0">No responses yet</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($responses as $response): ?>
                                            <div class="response mb-3 <?= $response['admin_id'] ? 'admin-response' : '' ?>">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <strong>
                                                        <?= $response['admin_id'] 
                                                            ? '<i class="fas fa-user-shield me-1"></i> ' . htmlspecialchars($response['admin_name'] ?? 'Admin')
                                                            : '<i class="fas fa-user me-1"></i> ' . htmlspecialchars($feedback['first_name'] . ' ' . $feedback['last_name'])
                                                        ?>
                                                    </strong>
                                                    <small class="text-muted">
                                                        <?= date('M j, Y \a\t g:i A', strtotime($response['created_at'])) ?>
                                                    </small>
                                                </div>
                                                <p class="mb-0"><?= nl2br(htmlspecialchars($response['response'])) ?></p>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="far fa-clock me-1"></i> 
                                Updated <?= date('M j, Y \a\t g:i A', strtotime($feedback['updated_at'])) ?>
                            </small>
                            <div>
                                <a href="mailto:<?= htmlspecialchars($feedback['email']) ?>" class="btn btn-sm btn-outline-secondary me-1">
                                    <i class="fas fa-envelope me-1"></i> Email User
                                </a>
                                <button class="btn btn-sm btn-outline-danger" 
                                        onclick="if(confirm('Are you sure you want to delete this feedback?')) { 
                                            window.location.href='delete-feedback.php?id=<?= $feedback['id'] ?>' 
                                        }">
                                    <i class="fas fa-trash-alt me-1"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Feedback pagination" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?status=<?= $status ?>&page=<?= $page - 1 ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?status=<?= $status ?>&page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?status=<?= $status ?>&page=<?= $page + 1 ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                    
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar on mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.admin-dashboard').classList.toggle('sidebar-toggled');
        });
        
        // Auto-expand textarea when typing
        document.querySelectorAll('textarea').forEach(textarea => {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
        });
        
        // Show success message if in URL
        if (window.location.search.includes('success=1')) {
            const alert = document.createElement('div');
            alert.className = 'alert alert-success alert-dismissible fade show';
            alert.role = 'alert';
            alert.innerHTML = `
                <i class="fas fa-check-circle me-2"></i>
                Operation completed successfully!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            document.querySelector('.container-fluid').prepend(alert);
            
            // Remove success parameter from URL
            const url = new URL(window.location);
            url.searchParams.delete('success');
            window.history.replaceState({}, '', url);
        }
    </script>
</body>
</html>

if ($priority_filter) {
    $where_clauses[] = "f.priority = ?";
    $params[] = $priority_filter;
}

if ($search) {
    $where_clauses[] = "(f.title LIKE ? OR f.description LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Get feedback
$stmt = $db->prepare("
    SELECT f.*, u.first_name, u.last_name, c.name as category_name, c.icon as category_icon
    FROM feedback f
    LEFT JOIN users u ON f.user_id = u.id
    JOIN categories c ON f.category_id = c.id
    {$where_sql}
    ORDER BY f.created_at DESC
");
$stmt->execute($params);
$feedback_list = $stmt->fetchAll();

// Get categories for filter
$categories = $db->query("SELECT * FROM categories WHERE is_active = TRUE ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Feedback - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-user-shield me-2"></i>Admin Panel
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-home me-1"></i>User Dashboard
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($user['first_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0 dashboard-sidebar">
                <div class="py-3">
                    <a href="dashboard.php" class="sidebar-link">
                        <i class="fas fa-chart-line me-2"></i>Dashboard
                    </a>
                    <a href="manage-feedback.php" class="sidebar-link active">
                        <i class="fas fa-comments me-2"></i>Manage Feedback
                    </a>
                    <a href="manage-users.php" class="sidebar-link">
                        <i class="fas fa-users me-2"></i>Manage Users
                    </a>
                    <a href="manage-categories.php" class="sidebar-link">
                        <i class="fas fa-tags me-2"></i>Categories
                    </a>
                    <a href="social-media.php" class="sidebar-link">
                        <i class="fas fa-hashtag me-2"></i>Social Media
                    </a>
                    <a href="reports.php" class="sidebar-link">
                        <i class="fas fa-file-alt me-2"></i>Reports
                    </a>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 dashboard-content">
                <div class="mb-4">
                    <h2>Manage Feedback</h2>
                    <p class="text-muted">Review and respond to student feedback</p>
                </div>

                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>Feedback updated successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <input type="text" class="form-control" name="search" placeholder="Search..." 
                                           value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="col-md-2">
                                    <select class="form-select" name="status">
                                        <option value="">All Status</option>
                                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="under_review" <?php echo $status_filter === 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                                        <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                        <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select class="form-select" name="category">
                                        <option value="">All Categories</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo $cat['id']; ?>" 
                                                    <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select class="form-select" name="priority">
                                        <option value="">All Priority</option>
                                        <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Low</option>
                                        <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                        <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>High</option>
                                        <option value="urgent" <?php echo $priority_filter === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-filter me-2"></i>Filter
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Feedback List -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Title</th>
                                        <th>User</th>
                                        <th>Category</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($feedback_list)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4">
                                                <i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i>
                                                <p class="text-muted">No feedback found</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($feedback_list as $feedback): ?>
                                            <tr>
                                                <td><?php echo $feedback['id']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($feedback['title']); ?></strong>
                                                </td>
                                                <td>
                                                    <?php 
                                                    if ($feedback['is_anonymous']) {
                                                        echo '<em>Anonymous</em>';
                                                    } else {
                                                        echo htmlspecialchars($feedback['first_name'] . ' ' . $feedback['last_name']);
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <i class="fas <?php echo $feedback['category_icon']; ?> me-1"></i>
                                                    <?php echo htmlspecialchars($feedback['category_name']); ?>
                                                </td>
                                                <td><?php echo getPriorityBadge($feedback['priority']); ?></td>
                                                <td><?php echo getStatusBadge($feedback['status']); ?></td>
                                                <td><?php echo timeAgo($feedback['created_at']); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" 
                                                            onclick="viewFeedback(<?php echo $feedback['id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-success" 
                                                            onclick="updateStatus(<?php echo $feedback['id']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Update Feedback Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="feedback_id" id="modal_feedback_id">
                        <input type="hidden" name="update_status" value="1">
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" name="status" id="modal_status" required>
                                <option value="pending">Pending</option>
                                <option value="under_review">Under Review</option>
                                <option value="in_progress">In Progress</option>
                                <option value="resolved">Resolved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="admin_response" class="form-label">Admin Response</label>
                            <textarea class="form-control" name="admin_response" id="modal_admin_response" rows="4" 
                                      placeholder="Provide a response to the user..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewFeedback(id) {
            window.location.href = 'view-feedback.php?id=' + id;
        }
        
        function updateStatus(id) {
            document.getElementById('modal_feedback_id').value = id;
            const modal = new bootstrap.Modal(document.getElementById('updateStatusModal'));
            modal.show();
        }
    </script>
</body>
</html>
