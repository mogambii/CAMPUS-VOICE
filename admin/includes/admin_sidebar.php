<?php if (!defined('IN_CAMPUS_VOICE')) die('Direct access not allowed'); ?>
<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h3>Campus Voice</h3>
        <span>Admin Panel</span>
    </div>
    
    <ul class="list-unstyled">
        <li class="active">
            <a href="index.php">
                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
            </a>
        </li>
        
        <li class="sidebar-section">Feedback Management</li>
        <li>
            <a href="manage-feedback.php">
                <i class="fas fa-comment-dots me-2"></i> All Feedback
                <span class="badge float-end" style="background-color: var(--primary-color); color: var(--light-color);">
                    <?php 
                    $db = getDB();
                    $count = $db->query("SELECT COUNT(*) FROM feedback")->fetchColumn();
                    echo $count;
                    ?>
                </span>
            </a>
        </li>
        <li>
            <a href="manage-feedback.php?status=pending">
                <i class="fas fa-clock me-2"></i> Pending
                <span class="badge float-end" style="background-color: var(--warning-color); color: #fff;">
                    <?php 
                    $count = $db->query("SELECT COUNT(*) FROM feedback WHERE status = 'pending'")->fetchColumn();
                    echo $count;
                    ?>
                </span>
            </a>
        </li>
        <li>
            <a href="manage-feedback.php?status=resolved">
                <i class="fas fa-check-circle me-2"></i> Resolved
            </a>
        </li>
        <li>
            <a href="reports.php">
                <i class="fas fa-chart-bar me-2"></i> Reports
            </a>
        </li>
        
        <li class="sidebar-section">Polls & Surveys</li>
        <li class="<?= (basename($_SERVER['PHP_SELF']) == 'polls.php') ? 'active' : '' ?>">
            <a href="polls.php" class="d-flex align-items-center">
                <i class="fas fa-poll me-2"></i> Manage Polls
                <span class="ms-auto badge bg-primary rounded-pill">
                    <?php 
                    $count = $db->query("SELECT COUNT(*) FROM polls WHERE (end_date IS NULL OR end_date >= CURDATE()) AND (start_date IS NULL OR start_date <= CURDATE())")->fetchColumn();
                    echo $count;
                    ?>
                </span>
            </a>
        </li>
        <li class="<?= (basename($_SERVER['PHP_SELF']) == 'new-poll.php') ? 'active' : '' ?>">
            <a href="new-poll.php" class="d-flex align-items-center">
                <i class="fas fa-plus-circle me-2"></i> Create New Poll
            </a>
        </li>
        
        <li class="sidebar-section">User Management</li>
        <li>
            <a href="users.php">
                <i class="fas fa-users me-2"></i> Manage Users
            </a>
        </li>
        
        <li class="sidebar-section">System</li>
        <li>
            <a href="../social-feed.php" target="_blank">
                <i class="fab fa-hashtag me-2"></i> Social Media Feed
            </a>
        </li>
        
        <li class="mt-4">
            <a href="logout.php" class="text-danger">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </a>
        </li>
    </ul>
</div>
