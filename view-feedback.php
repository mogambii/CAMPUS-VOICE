<?php
require_once 'includes/functions.php';
requireLogin();

$user = getCurrentUser();
$db = getDB();

$feedback_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$view_mode = isset($_GET['user']) && $_GET['user'] === 'me' ? 'user_list' : 'single';
$feedback = []; // Initialize feedback as empty array
$user_feedback = []; // Initialize user_feedback as empty array

// Handle viewing a single feedback item
if ($view_mode === 'single' && $feedback_id > 0) {
    // Get single feedback details
    $stmt = $db->prepare("
        SELECT f.*, u.first_name, u.last_name, u.email, c.name as category_name, 'fa-folder' as category_icon
        FROM feedback f
        LEFT JOIN users u ON f.user_id = u.id
        JOIN categories c ON f.category_id = c.id
        WHERE f.id = ?
    ");
    $stmt->execute([$feedback_id]);
    $feedback = $stmt->fetch();

    if (!$feedback) {
        header('Location: dashboard.php');
        exit();
    }

    // Check if user has permission to view
    if (!isAdmin() && $feedback['user_id'] != $user['id']) {
        header('Location: dashboard.php');
        exit();
    }
} 
// Handle viewing user's feedback list
else if ($view_mode === 'user_list') {
    // Get all feedback for the current user with LEFT JOIN to handle missing categories
    $stmt = $db->prepare("
        SELECT f.*, c.name as category_name, 'fa-folder' as category_icon
        FROM feedback f
        LEFT JOIN categories c ON f.category_id = c.id
        WHERE f.user_id = ?
        ORDER BY f.created_at DESC
    ");
    
    // Debug: Show the SQL query
    error_log('SQL Query: ' . $stmt->queryString);
    $stmt->execute([$user['id']]);
    $user_feedback = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Show the raw feedback data
    error_log('Raw feedback data: ' . print_r($user_feedback, true));
    
    // Debug output (temporary)
    error_log('User ID: ' . $user['id']);
    error_log('Number of feedback items found: ' . count($user_feedback));
    
    // Direct database verification
    $verify_stmt = $db->query("SELECT COUNT(*) as count FROM feedback WHERE user_id = " . $user['id']);
    $count = $verify_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    error_log('Direct DB count for user feedback: ' . $count);
    
    // If we found feedback directly but not through the join, there might be a category issue
    if ($count > 0 && count($user_feedback) === 0) {
        $verify_feedback = $db->query("SELECT * FROM feedback WHERE user_id = " . $user['id'])->fetchAll();
        error_log('First feedback item: ' . print_r($verify_feedback[0] ?? 'No feedback', true));
        
        // Try to get category for the first feedback
        if (!empty($verify_feedback)) {
            $cat_stmt = $db->query("SELECT * FROM categories WHERE id = " . $verify_feedback[0]['category_id']);
            $category = $cat_stmt->fetch(PDO::FETCH_ASSOC);
            error_log('Category check: ' . print_r($category, true));
        }
    }
    
    // If there's only one feedback item, redirect to it directly
    if (count($user_feedback) === 1) {
        header('Location: view-feedback.php?id=' . $user_feedback[0]['id']);
        exit();
    }
} else {
    // Invalid access
    header('Location: dashboard.php');
    exit();
}

// Get responses from feedback_responses table
$responses_stmt = $db->prepare("
    SELECT fr.*, u.first_name, u.last_name, u.role
    FROM feedback_responses fr
    LEFT JOIN users u ON fr.user_id = u.id
    WHERE fr.feedback_id = ?
    ORDER BY fr.created_at ASC
");
$responses_stmt->execute([$feedback_id]);
$responses = $responses_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    $comment = sanitize($_POST['comment']);
    
    if (!empty($comment)) {
        $insert_stmt = $db->prepare("
            INSERT INTO feedback_comments (feedback_id, user_id, comment, is_admin_response)
            VALUES (?, ?, ?, ?)
        ");
        $insert_stmt->execute([$feedback_id, $user['id'], $comment, isAdmin() ? 1 : 0]);
        
        // Send notification
        if (isAdmin()) {
            sendNotification($feedback['user_id'], 'New Response', "Admin responded to your feedback: {$feedback['title']}", 'comment', $feedback_id);
        }
        
        header('Location: view-feedback.php?id=' . $feedback_id);
        exit();
    }
}

// Update view count
//$db->prepare("UPDATE feedback SET views = views + 1 WHERE id = ?")->execute([$feedback_id]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ($view_mode === 'single' && !empty($feedback)) ? htmlspecialchars($feedback['title'] . ' - Campus Voice') : 'My Feedback - Campus Voice'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-comment-dots me-2"></i>Campus Voice
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($user['first_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="dashboard.php"><i class="fas fa-home me-2"></i>Dashboard</a></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <style>
        .feedback-card {
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease-in-out;
        }
        .feedback-card:hover {
            transform: translateY(-2px);
        }
        .card-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .category-badge {
            background: linear-gradient(45deg, #6c5ce7, #a29bfe);
            color: white;
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        .back-btn {
            transition: all 0.3s ease;
        }
        .back-btn:hover {
            transform: translateX(-3px);
        }
        .description-box {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 1.25rem;
        }
        .timeline-item {
            border-left: 3px solid #6c5ce7;
            padding-left: 1.5rem;
            position: relative;
            margin-bottom: 1.5rem;
        }
        .timeline-item:last-child {
            margin-bottom: 0;
        }
        .timeline-marker {
            position: absolute;
            left: -10px;
            top: 0;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #6c5ce7;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.75rem;
        }
    </style>

    <div class="container py-4">
        <?php if ($view_mode === 'single' && !empty($feedback)): ?>
            <div class="row justify-content-center">
                <div class="col-lg-9">
                    <!-- Feedback Details Card -->
                    <div class="card mb-4 feedback-card">
                        <div class="card-header py-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h4 class="mb-2"><?php echo isset($feedback['title']) ? htmlspecialchars($feedback['title']) : 'Feedback Details'; ?></h4>
                                    <div class="d-flex gap-2 flex-wrap align-items-center">
                                        <span class="badge category-badge rounded-pill px-3 py-2">
                                            <i class="fas <?php echo $feedback['category_icon']; ?> me-2"></i>
                                            <?php echo htmlspecialchars($feedback['category_name']); ?>
                                        </span>
                                        <span class="text-muted small">
                                            <i class="far fa-calendar-alt me-1"></i>
                                            <?php echo formatDate($feedback['created_at']); ?>
                                        </span>
                                    </div>
                                </div>
                                <a href="dashboard.php" class="btn btn-outline-primary back-btn">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="description-box mb-4">
                                <h6 class="text-primary mb-3 d-flex align-items-center">
                                    <i class="fas fa-align-left me-2"></i>
                                    Description
                                </h6>
                                <div class="ps-3 border-start border-3 border-primary">
                                    <p class="mb-0 text-dark"><?php echo nl2br(htmlspecialchars($feedback['description'])); ?></p>
                                </div>
                            </div>
                            
                            <?php if (isset($feedback['location']) && !empty($feedback['location'])): ?>
                            <div class="mb-4">
                                <h6 class="text-muted mb-2">Location</h6>
                                <p class="mb-0">
                                    <i class="fas fa-map-marker-alt me-2"></i>
                                    <?php echo htmlspecialchars($feedback['location']); ?>
                                </p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($feedback['file_path'])): ?>
                            <div class="mb-4">
                                <h6 class="text-muted mb-2">Attachment</h6>
                                <a href="<?php echo htmlspecialchars($feedback['file_path']); ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
                                    <i class="fas fa-paperclip me-1"></i>View Attachment
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-between align-items-center flex-wrap">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="me-3">
                                        <?php if (!empty($feedback['anonymous']) && $feedback['anonymous'] == 1): ?>
                                            <span class="badge bg-secondary">Anonymous</span>
                                        <?php else: ?>
                                            <span class="fw-bold">
                                                <?php 
                                                    $name = [];
                                                    if (isset($feedback['first_name'])) $name[] = $feedback['first_name'];
                                                    if (isset($feedback['last_name'])) $name[] = $feedback['last_name'];
                                                    echo !empty($name) ? htmlspecialchars(implode(' ', $name)) : 'Unknown User';
                                                ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="text-muted small">
                                        <i class="far fa-envelope me-1"></i>
                                        <?php echo isset($feedback['email']) ? htmlspecialchars($feedback['email']) : 'N/A'; ?>
                                    </div>
                                </div>
                                
                                <div>
                                    <?php if (isset($feedback['views'])): ?>
                                    <div>
                                        <i class="fas fa-eye me-1"></i>
                                        <?php echo intval($feedback['views']); ?> views
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Status Timeline -->
                        <div class="card-footer bg-white">
                            <h6 class="mb-3">Status Timeline</h6>
                            <div class="timeline">
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-primary"></div>
                                    <div class="timeline-content">
                                        <small class="text-muted">Submitted</small>
                                        <p class="mb-0"><?php echo isset($feedback['created_at']) ? formatDate($feedback['created_at']) : 'N/A'; ?></p>
                                    </div>
                                </div>
                                
                                <?php if (isset($feedback['in_progress_at']) && !empty($feedback['in_progress_at'])): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-marker bg-info"></div>
                                        <div class="timeline-content">
                                            <small class="text-muted">In Progress</small>
                                            <p class="mb-0"><?php echo formatDate($feedback['in_progress_at']); ?></p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (isset($feedback['resolved_at']) && !empty($feedback['resolved_at'])): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-marker bg-success"></div>
                                        <div class="timeline-content">
                                            <small class="text-muted">Resolved</small>
                                            <p class="mb-0"><?php echo formatDate($feedback['resolved_at']); ?></p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Responses Section -->
                    <div class="card mt-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">
                                <i class="fas fa-comments me-2"></i>Responses
                                <?php if (!empty($responses)): ?>
                                    <span class="badge bg-primary rounded-pill ms-2"><?php echo count($responses); ?></span>
                                <?php endif; ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($responses)): ?>
                                <div class="text-center py-3 text-muted">
                                    <i class="fas fa-comment-slash fa-2x mb-2"></i>
                                    <p class="mb-0">No responses yet</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($responses as $response): ?>
                                    <div class="mb-3 pb-3 border-bottom <?php echo isset($response['role']) && $response['role'] === 'admin' ? 'border-primary' : ''; ?>">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <strong class="d-flex align-items-center">
                                                    <?php if (isset($response['role']) && $response['role'] === 'admin'): ?>
                                                        <i class="fas fa-user-shield me-2 text-primary"></i>
                                                        Admin
                                                    <?php else: ?>
                                                        <i class="fas fa-user me-2"></i>
                                                        <?php echo htmlspecialchars($response['first_name'] . ' ' . $response['last_name']); ?>
                                                    <?php endif; ?>
                                                </strong>
                                            </div>
                                            <small class="text-muted">
                                                <i class="far fa-clock me-1"></i>
                                                <?php echo date('M j, Y \a\t g:i A', strtotime($response['created_at'])); ?>
                                            </small>
                                        </div>
                                        <div class="response-content">
                                            <?php echo nl2br(htmlspecialchars($response['response'])); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Admin Actions -->
                    <?php if (isAdmin()): ?>
                        <div class="mt-4 d-flex gap-2">
                            <a href="admin/respond-feedback.php?id=<?php echo $feedback_id; ?>" class="btn btn-primary">
                                <i class="fas fa-reply me-1"></i>Respond
                            </a>
                            <a href="admin/update-status.php?id=<?php echo $feedback_id; ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-edit me-1"></i>Update Status
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Sidebar -->
                <div class="col-lg-3">
                    <!-- Status Card -->
                    <div class="card mb-4">
                        <div class="card-header bg-white">
                            <h6 class="mb-0">Status</h6>
                        </div>
                        <div class="card-body text-center py-4">
                            <div class="mb-3">
                                <?php 
                                if (isset($feedback['status'])) {
                                    // Ensure status is in the correct format (lowercase, no spaces)
                                    $status = strtolower(str_replace(' ', '_', $feedback['status']));
                                    $status_text = ucwords(str_replace('_', ' ', $status));
                                    echo '<span class="badge ' . getStatusBadge($status) . '">' . $status_text . '</span>';
                                } else {
                                    echo '<span class="badge bg-secondary">Unknown</span>';
                                }
                                ?>
                            </div>
                            </div>
                            <h5 class="mb-0">
                    
                            </h5>
                        </div>
                    </div>
                    
                    <!-- Feedback Details -->
                    <div class="card">
                        <div class="card-header bg-white">
                            <h6 class="mb-0">Details</h6>
                        </div>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span class="text-muted">Submitted</span>
                                <span><?php echo isset($feedback['created_at']) ? formatDate($feedback['created_at'], 'M d, Y') : 'N/A'; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span class="text-muted">Last Updated</span>
                                <span><?php 
                                    $lastUpdated = '';
                                    if (isset($feedback['updated_at'])) {
                                        $lastUpdated = $feedback['updated_at'];
                                    } elseif (isset($feedback['created_at'])) {
                                        $lastUpdated = $feedback['created_at'];
                                    }
                                    echo $lastUpdated ? formatDate($lastUpdated, 'M d, Y') : 'N/A';
                                ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span class="text-muted">Category</span>
                                <span><?php echo isset($feedback['category_name']) ? htmlspecialchars($feedback['category_name']) : 'N/A'; ?></span>
                            </li>
                            <?php if (!empty($feedback['location'])): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span class="text-muted">Location</span>
                                <span><?php echo htmlspecialchars($feedback['location']); ?></span>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
       
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e9ecef;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }
        .timeline-marker {
            position: absolute;
            left: -26px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 3px solid #fff;
        }
        .timeline-content {
            padding-left: 10px;
        }
    </style>
</body>
</html>
        <?php else: ?>
                <h2>My Feedback</h2>
                <a href="submit-feedback.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Submit New Feedback
                </a>
            </div>
            
            <?php if (empty($user_feedback)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    You haven't submitted any feedback yet. Click the button above to get started.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_feedback as $item): ?>
                                <tr>
                                    <td>
                                        <a href="view-feedback.php?id=<?php echo $item['id']; ?>">
                                            <?php echo htmlspecialchars($item['title']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                    <td><?php echo getStatusBadge($item['status'] ?? 'pending'); ?></td>
                                    <td><?php echo formatDate($item['created_at'] ?? '', 'M d, Y'); ?></td>
                                    <td>
                                        <a href="view-feedback.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if (empty($user_feedback)): ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <div class="mb-3">
                            <i class="fas fa-comment-slash fa-4x text-muted"></i>
                        </div>
                        <h4 class="text-muted">No feedback submitted yet</h4>
                        <p class="text-muted">Click the button above to submit your first feedback</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Submitted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($user_feedback as $item): ?>
                                    <tr>
                                        <td>
                                            <a href="view-feedback.php?id=<?php echo $item['id']; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($item['title']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark">
                                                <i class="fas <?php echo htmlspecialchars($item['category_icon']); ?> me-1"></i>
                                                <?php echo htmlspecialchars($item['category_name']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo getStatusBadge($item['status']); ?>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo formatDate($item['created_at'], 'M d, Y'); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <a href="view-feedback.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="far fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e9ecef;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }
        .timeline-marker {
            position: absolute;
            left: -26px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 3px solid #fff;
        }
        .timeline-content {
            padding-left: 10px;
        }
    </style>
</body>
</html>
