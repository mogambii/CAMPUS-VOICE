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
    
    $update = $db->prepare("UPDATE feedback SET status = ? WHERE id = ?");
    if ($update->execute([$new_status, $feedback_id])) {
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
       $insert = $db->prepare("INSERT INTO feedback_responses (feedback_id, response, created_at, user_id) 
                       VALUES (?, ?, NOW(), ?)");
if ($insert->execute([$feedback_id, $response, $_SESSION['user_id']])) {
            // Response added successfully
            $stmt = $db->prepare("SELECT user_id, title FROM feedback WHERE id = ?");
            $stmt->execute([$feedback_id]);
            $feedback = $stmt->fetch();
            
            if ($feedback) {
                // Get the ID of the newly created response
                $responseId = $db->lastInsertId();
                
                // Send notification to the user
                $notification = $db->prepare("INSERT INTO notifications 
                    (user_id, title, message, type, reference_id, created_at) 
                    VALUES (?, ?, ?, 'feedback_response', ?, NOW())");
                $notification->execute([
                    $feedback['user_id'],
                    'New Response to Your Feedback',
                    'You have received a new response to your feedback: ' . $feedback['title'],
                    $responseId
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
        /* Sidebar Toggle Styles */
        @media (max-width: 991.98px) {
            .sidebar {
                margin-left: -250px;
                transition: margin 0.3s ease;
            }
            .sidebar.toggled {
                margin-left: 0;
            }
            #sidebarToggle {
                display: block !important;
                transition: left 0.3s ease;
            }
            .sidebar.toggled + .flex-grow-1 #sidebarToggle {
                left: 250px;
            }
        }
        
        .status-badge {
            font-size: 0.8rem;
            padding: 0.35em 0.65em;
        }
        .feedback-card {
            transition: all 0.3s ease;
            border-left: 4px solid #dee2e6;
            margin-bottom: 1.5rem;
        }
        body, html {
            width: 100%;
            margin: 0;
            padding: 0;
        }
        body {
            overflow-x: hidden;
        }
        .container-fluid {
            max-width: 100% !important;
            padding: 0 !important;
            margin: 0 !important;
            width: 100% !important;
        }
        .px-4 {
            padding-left: 1.5rem !important;
            padding-right: 1.5rem !important;
        }
        .py-3 {
            padding-top: 1rem !important;
            padding-bottom: 1rem !important;
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
            <!-- Burger Menu Button for Tablet -->
            <button class="btn btn-link text-dark d-lg-none position-fixed p-3" id="sidebarToggle" style="z-index: 1000; left: 0; top: 0;">
                <i class="fas fa-bars fa-lg"></i>
            </button>
            <!-- Main Content - Full Width -->
            <div class="container-fluid p-0 m-0 w-100" style="position: relative; left: 0; right: 0;">
                <div class="w-100" style="padding: 1.5rem;">
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
                                <?php if (!empty($feedback['updated_at'])): ?>
                                    Updated <?= date('M j, Y \a\t g:i A', strtotime($feedback['updated_at'])) ?>
                                <?php else: ?>
                                    Not updated yet
                                <?php endif; ?>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar on mobile/tablet
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.querySelector('.sidebar');
        
        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', function(e) {
                e.preventDefault();
                document.body.classList.toggle('sidebar-toggled');
                sidebar.classList.toggle('toggled');
                
                // Toggle between hamburger and close icon
                const icon = this.querySelector('i');
                if (icon.classList.contains('fa-bars')) {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-times');
                } else {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            });
        }
        
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