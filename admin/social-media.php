<?php
require_once '../includes/functions.php';
requireLogin();

if (!isAdmin()) {
    header('Location: ../dashboard.php');
    exit();
}

$user = getCurrentUser();
$db = getDB();

// Get social media posts
$posts_stmt = $db->query("
    SELECT * FROM social_media_posts
    ORDER BY posted_at DESC
    LIMIT 50
");
$social_posts = $posts_stmt->fetchAll();

// Get statistics
$stats_stmt = $db->query("
    SELECT 
        platform,
        COUNT(*) as count,
        SUM(CASE WHEN is_processed = TRUE THEN 1 ELSE 0 END) as processed
    FROM social_media_posts
    GROUP BY platform
");
$platform_stats = $stats_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Social Media Integration - Admin Panel</title>
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
                        <a class="nav-link" href="../dashboard.php">
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
                    <a href="manage-feedback.php" class="sidebar-link">
                        <i class="fas fa-comments me-2"></i>Manage Feedback
                    </a>
                    <a href="manage-users.php" class="sidebar-link">
                        <i class="fas fa-users me-2"></i>Manage Users
                    </a>
                    <a href="manage-categories.php" class="sidebar-link">
                        <i class="fas fa-tags me-2"></i>Categories
                    </a>
                    <a href="social-media.php" class="sidebar-link active">
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
                    <h2><i class="fas fa-hashtag me-2"></i>Social Media Integration</h2>
                    <p class="text-muted">Monitor and manage campus-related social media posts</p>
                </div>

                <!-- Action Buttons -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary" onclick="fetchTwitterPosts()">
                                <i class="fab fa-twitter me-2"></i>Fetch Twitter Posts
                            </button>
                            <button class="btn btn-primary" onclick="fetchInstagramPosts()">
                                <i class="fab fa-instagram me-2"></i>Fetch Instagram Posts
                            </button>
                            <button class="btn btn-outline-primary" onclick="refreshPosts()">
                                <i class="fas fa-sync-alt me-2"></i>Refresh
                            </button>
                        </div>
                        <div id="fetchStatus" class="mt-3"></div>
                    </div>
                </div>

                <!-- Platform Statistics -->
                <div class="row g-4 mb-4">
                    <?php foreach ($platform_stats as $stat): ?>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-card-icon primary">
                                    <i class="fab fa-<?php echo $stat['platform']; ?>"></i>
                                </div>
                                <h3><?php echo $stat['count']; ?></h3>
                                <p class="text-muted mb-0"><?php echo ucfirst($stat['platform']); ?> Posts</p>
                                <small class="text-success"><?php echo $stat['processed']; ?> processed</small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Monitored Hashtags -->
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Monitored Hashtags</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach (CAMPUS_HASHTAGS as $hashtag): ?>
                                <span class="badge bg-primary fs-6"><?php echo $hashtag; ?></span>
                            <?php endforeach; ?>
                        </div>
                        <p class="text-muted mt-3 mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            These hashtags are automatically monitored across social media platforms
                        </p>
                    </div>
                </div>

                <!-- Social Media Posts -->
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Recent Social Media Posts</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($social_posts)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-hashtag fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No social media posts found. Click "Fetch" to retrieve posts.</p>
                            </div>
                        <?php else: ?>
                            <div class="row g-3">
                                <?php foreach ($social_posts as $post): ?>
                                    <div class="col-md-6">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div>
                                                        <i class="fab fa-<?php echo $post['platform']; ?> fa-2x text-primary"></i>
                                                    </div>
                                                    <span class="badge bg-<?php echo $post['is_processed'] ? 'success' : 'warning'; ?>">
                                                        <?php echo $post['is_processed'] ? 'Processed' : 'Pending'; ?>
                                                    </span>
                                                </div>
                                                
                                                <h6 class="card-title">
                                                    <?php echo htmlspecialchars($post['author_name'] ?? $post['author_username'] ?? 'Unknown'); ?>
                                                </h6>
                                                
                                                <p class="card-text"><?php echo htmlspecialchars($post['content']); ?></p>
                                                
                                                <div class="d-flex justify-content-between align-items-center mt-3">
                                                    <small class="text-muted">
                                                        <?php echo timeAgo($post['posted_at'] ?? $post['fetched_at']); ?>
                                                    </small>
                                                    <div>
                                                        <?php if ($post['post_url']): ?>
                                                            <a href="<?php echo htmlspecialchars($post['post_url']); ?>" 
                                                               target="_blank" class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-external-link-alt"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <button class="btn btn-sm btn-outline-success" 
                                                                onclick="convertToFeedback(<?php echo $post['id']; ?>)">
                                                            <i class="fas fa-arrow-right"></i> Convert
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showStatus(message, type = 'info') {
            const statusDiv = document.getElementById('fetchStatus');
            statusDiv.innerHTML = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
        }
        
        async function fetchTwitterPosts() {
            showStatus('<i class="fas fa-spinner fa-spin me-2"></i>Fetching Twitter posts...', 'info');
            
            try {
                const response = await fetch('../api/social_media.php?action=fetch_twitter');
                const data = await response.json();
                
                if (data.success) {
                    showStatus(`Successfully fetched ${data.fetched} posts, saved ${data.saved} new posts`, 'success');
                    setTimeout(() => refreshPosts(), 2000);
                } else {
                    showStatus(data.message || 'Failed to fetch Twitter posts', 'danger');
                }
            } catch (error) {
                showStatus('Error: ' + error.message, 'danger');
            }
        }
        
        async function fetchInstagramPosts() {
            showStatus('<i class="fas fa-spinner fa-spin me-2"></i>Fetching Instagram posts...', 'info');
            
            try {
                const response = await fetch('../api/social_media.php?action=fetch_instagram');
                const data = await response.json();
                
                if (data.success) {
                    showStatus(`Successfully fetched ${data.fetched} posts, saved ${data.saved} new posts`, 'success');
                    setTimeout(() => refreshPosts(), 2000);
                } else {
                    showStatus(data.message || 'Failed to fetch Instagram posts', 'danger');
                }
            } catch (error) {
                showStatus('Error: ' + error.message, 'danger');
            }
        }
        
        function refreshPosts() {
            location.reload();
        }
        
        function convertToFeedback(postId) {
            if (confirm('Convert this social media post to feedback?')) {
                // Implementation for converting social media post to feedback
                alert('This feature will convert the post to a feedback entry');
            }
        }
    </script>
</body>
</html>
