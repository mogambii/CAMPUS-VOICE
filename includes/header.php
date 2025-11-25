<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);

// Get user data if logged in
if ($is_logged_in) {
    $user = [
        'first_name' => $_SESSION['first_name'] ?? 'User',
        'last_name' => $_SESSION['last_name'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'role' => $_SESSION['role'] ?? 'user'
    ];
    
    // Get notification count (you'll need to implement this function)
    $notif_count = 0; // Replace with actual notification count logic
}
?>
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
            <?php if ($is_logged_in): ?>
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-home me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="submit-feedback.php">
                            <i class="fas fa-plus-circle me-1"></i> Submit Feedback
                        </a>
                    </li>
                    <?php if (isset($user['role']) && $user['role'] === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="admin/index.php">
                                <i class="fas fa-shield-alt me-1"></i> Admin
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item d-flex align-items-center me-3">
                        <a class="nav-link position-relative" href="notifications.php" title="Notifications" id="notification-bell">
                            <i class="fas fa-bell fs-5"></i>
                            <span id="notification-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger <?php echo ($notif_count > 0) ? '' : 'd-none'; ?>" style="font-size: 0.6rem;">
                                <?php echo $notif_count > 0 ? $notif_count : ''; ?>
                                <span class="visually-hidden">unread notifications</span>
                            </span>
                        </a>
                    </li>
                    <script>
                    // Auto-update notification badge
                    function updateNotificationBadge() {
                        fetch('api/notifications/count.php')
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    const badge = document.getElementById('notification-badge');
                                    if (data.unread > 0) {
                                        badge.textContent = data.unread;
                                        badge.classList.remove('d-none');
                                    } else {
                                        badge.classList.add('d-none');
                                    }
                                }
                            })
                            .catch(error => console.error('Error updating notifications:', error));
                    }
                    
                    // Update immediately and then every 30 seconds
                    updateNotificationBadge();
                    setInterval(updateNotificationBadge, 30000);
                    
                    // Mark notifications as read when bell is clicked
                    document.getElementById('notification-bell').addEventListener('click', function() {
                        // The actual marking as read happens on the notifications page
                        // But we can update the UI immediately for better UX
                        document.getElementById('notification-badge').classList.add('d-none');
                    });
                    </script>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <div class="me-2 d-flex align-items-center">
                                <div class="position-relative">
                                    <i class="fas fa-user-circle fs-4"></i>
                                </div>
                            </div>
                            <div class="d-flex flex-column">
                                <span class="fw-medium"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                                <small class="text-white-50">View Profile</small>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>My Profile</a></li>
                            <li><a class="dropdown-item" href="view-feedback.php?user=me"><i class="fas fa-comments me-2"></i>My Feedback</a></li>
                            <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Account Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            <?php else: ?>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">
                            <i class="fas fa-sign-in-alt me-1"></i> Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">
                            <i class="fas fa-user-plus me-1"></i> Register
                        </a>
                    </li>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Page Content -->
<div class="container py-4">
