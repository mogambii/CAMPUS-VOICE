<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/includes/functions.php';
$db = getDB();

// Initialize notification count
$notif_count = 0; // This should be replaced with actual notification logic

// Get user data from session with proper defaults
$user = [
    'first_name' => $_SESSION['first_name'] ?? 'User',
    'last_name' => $_SESSION['last_name'] ?? '',
    'email' => $_SESSION['email'] ?? '',
    'role' => $_SESSION['role'] ?? 'student',
    'id' => $_SESSION['user_id'] ?? null
];

// Initialize user feedback stats for regular users
$user_feedback_stats = [
    'submitted' => 0,
    'viewed' => 0,
    'resolved' => 0
];

try {
    if ($user['role'] === 'admin') {
        // Admin: Get all feedback statistics
        $feedback_stmt = $db->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress
            FROM feedback
        ");
        
        if ($feedback_stmt) {
            $feedback_stats = $feedback_stmt->fetch(PDO::FETCH_ASSOC) ?? $feedback_stats;
        }
        
        // Get recent feedback from all users
        $recent_feedback_stmt = $db->prepare("
            SELECT f.*, u.first_name, u.last_name, u.email, c.name as category_name, c.icon as category_icon
            FROM feedback f
            JOIN users u ON f.user_id = u.id
            LEFT JOIN categories c ON f.category_id = c.id
            ORDER BY f.created_at DESC
            LIMIT 5
        ");
        $recent_feedback_stmt->execute();
        $recent_feedback = $recent_feedback_stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else {
        // Regular user/student: Get their own feedback statistics
        $user_feedback_stmt = $db->prepare("
            SELECT 
                COUNT(*) as submitted,
                SUM(CASE WHEN status = 'viewed' THEN 1 ELSE 0 END) as viewed,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved
            FROM feedback
            WHERE user_id = ?
        ");
        $user_feedback_stmt->execute([$user['id']]);
        $user_feedback_stats = $user_feedback_stmt->fetch(PDO::FETCH_ASSOC) ?? $user_feedback_stats;
        
        // Get user's own recent feedback
        $recent_feedback_stmt = $db->prepare("
            SELECT f.*, c.name as category_name, c.icon as category_icon
            FROM feedback f
            LEFT JOIN categories c ON f.category_id = c.id
            WHERE f.user_id = ?
            ORDER BY f.created_at DESC
            LIMIT 5
        ");
        $recent_feedback_stmt->execute([$user['id']]);
        $recent_feedback = $recent_feedback_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    // Log error but don't stop execution
    error_log("Error fetching dashboard data: " . $e->getMessage());
}

// Get dashboard statistics
$stats = [
    'total_feedback' => $feedback_stats['total'] ?? 0,
    'active_polls' => 0,
    'pending_surveys' => 0,
    'duplicates_found' => 0
];

// Initialize feedback stats with default values for admin
$feedback_stats = [
    'total' => 0,
    'pending' => 0,
    'resolved' => 0,
    'in_progress' => 0
];

// Get social media metrics (placeholder data for now)
$social_metrics = [
    'twitter' => ['followers' => 0, 'mentions' => 0],
    'facebook' => ['followers' => 0, 'mentions' => 0],
    'instagram' => ['followers' => 0, 'mentions' => 0]
];

// Check for duplicate feedback (AI-powered)
$duplicate_alerts = [];
if (!empty($recent_feedback)) {
    foreach ($recent_feedback as $feedback) {
        if (isset($feedback['duplicate_count']) && $feedback['duplicate_count'] > 0) {
            $duplicate_alerts[] = [
                'id' => $feedback['id'],
                'title' => $feedback['title'] ?? 'Untitled',
                'duplicate_count' => $feedback['duplicate_count']
            ];
        }
    }
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Campus Voice</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary py-2 px-0">
        <div class="container-fluid px-3">
            <a class="navbar-brand fw-bold" href="dashboard.php">
                <i class="fas fa-comment-dots me-2"></i>Campus Voice
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item d-flex align-items-center">
                        <a class="nav-link" href="notifications.php" title="Notifications">
                            <i class="fas fa-bell fs-5"></i>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <div class="me-2 d-flex align-items-center">
                                <div class="position-relative">
                                    <i class="fas fa-user-circle fs-4"></i>
                                    <?php if ($notif_count > 0): ?>
                                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                            <?php echo $notif_count; ?>
                                            <span class="visually-hidden">unread notifications</span>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="d-flex flex-column">
                                <span class="fw-medium"><?php echo htmlspecialchars($user['first_name'] . ' ' . ($user['last_name'] ?? '')); ?></span>
                                <small class="text-white-50">View Profile</small>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>My Profile</a></li>
                            <li><a class="dropdown-item" href="view-feedback.php?user=me"><i class="fas fa-comments me-2"></i>My Feedback</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid p-0">
        <div class="row g-0 min-vh-100">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 dashboard-sidebar" id="sidebar">
                <div class="py-3">
                    <a href="dashboard.php" class="sidebar-link active">
                        <i class="fas fa-home me-2"></i>Dashboard
                    </a>
                    <a href="submit-feedback.php" class="sidebar-link">
                        <i class="fas fa-plus-circle me-2"></i>Submit Feedback
                    </a>
                    <a href="view-feedback.php?user=me" class="sidebar-link">
                        <i class="fas fa-list me-2"></i>My Feedback
                    </a>
                    <a href="polls.php" class="sidebar-link">
                        <i class="fas fa-poll me-2"></i>Polls & Surveys
                    </a>
                    <?php if ($user['role'] === 'admin'): ?>
                        <hr>
                        <a href="admin/manage-feedback.php" class="sidebar-link">
                            <i class="fas fa-tasks me-2"></i>Manage Feedback
                        </a>
                        <a href="admin/polls.php" class="sidebar-link">
                            <i class="fas fa-poll me-2"></i>Manage Polls
                        </a>
                        <a href="admin/users.php" class="sidebar-link">
                            <i class="fas fa-users me-2"></i>Manage Users
                        </a>
                        <a href="admin/settings.php" class="sidebar-link">
                            <i class="fas fa-cog me-2"></i>Settings
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Main Content -->
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 dashboard-content p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>! 
                        <?php if ($user['role'] === 'admin'): ?>
                            <span class="badge bg-primary ms-2">Admin</span>
                        <?php endif; ?>
                    </h2>
                    <?php if ($user['role'] !== 'admin'): ?>
                        <a href="submit-feedback.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Submit Feedback
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Statistics Cards -->
                <div class="row g-4 mb-4">
                    <?php if ($user['role'] === 'admin'): ?>
                        <!-- Admin Statistics -->
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-card-icon primary">
                                    <i class="fas fa-comments"></i>
                                </div>
                                <h3><?php echo htmlspecialchars($feedback_stats['total'] ?? 0); ?></h3>
                                <p class="text-muted mb-0">Total Feedback</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-card-icon warning">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <h3><?php echo htmlspecialchars($feedback_stats['pending'] ?? 0); ?></h3>
                                <p class="text-muted mb-0">Pending</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-card-icon primary">
                                    <i class="fas fa-spinner"></i>
                                </div>
                                <h3><?php echo htmlspecialchars($feedback_stats['in_progress'] ?? 0); ?></h3>
                                <p class="text-muted mb-0">In Progress</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-card-icon success">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <h3><?php echo htmlspecialchars($feedback_stats['resolved'] ?? 0); ?></h3>
                                <p class="text-muted mb-0">Resolved</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- User Statistics -->
                        <div class="col-md-4">
                            <div class="stat-card">
                                <div class="stat-card-icon primary">
                                    <i class="fas fa-paper-plane"></i>
                                </div>
                                <h3><?php echo htmlspecialchars($user_feedback_stats['submitted'] ?? 0); ?></h3>
                                <p class="text-muted mb-0">Submitted</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card">
                                <div class="stat-card-icon primary">
                                    <i class="fas fa-eye"></i>
                                </div>
                                <h3><?php echo htmlspecialchars($user_feedback_stats['viewed'] ?? 0); ?></h3>
                                <p class="text-muted mb-0">Viewed</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card">
                                <div class="stat-card-icon success">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <h3><?php echo htmlspecialchars($user_feedback_stats['resolved'] ?? 0); ?></h3>
                                <p class="text-muted mb-0">Resolved</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
