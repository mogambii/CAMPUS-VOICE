<?php
define('IN_CAMPUS_VOICE', true);
require_once 'includes/functions.php';
requireLogin();

$user = getCurrentUser();
$db = getDB();
$error = '';
$success = '';

// Mark notifications as read when viewing them
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_as_read'])) {
    try {
        $update = $db->prepare("UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL");
        $update->execute([$user['id']]);
        $success = 'Notifications marked as read';
    } catch (Exception $e) {
        $error = 'Error updating notifications: ' . $e->getMessage();
    }
}

// Get user's notifications with response details for feedback responses
try {
    $stmt = $db->prepare("
        SELECT 
            n.*,
            fr.response as feedback_response,
            fr.created_at as response_date,
            u.first_name as admin_first_name,
            u.last_name as admin_last_name,
            f.title as feedback_title
        FROM notifications n
        LEFT JOIN feedback_responses fr ON n.reference_id = fr.id AND n.type = 'feedback_response'
        LEFT JOIN feedback f ON fr.feedback_id = f.id
        LEFT JOIN users u ON fr.user_id = u.id
        WHERE n.user_id = ? 
        ORDER BY n.created_at DESC
    ");
    $stmt->execute([$user['id']]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unread count for the badge
    $unreadStmt = $db->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND read_at IS NULL");
    $unreadStmt->execute([$user['id']]);
    $unreadCount = $unreadStmt->fetch()['unread_count'];
    
} catch (Exception $e) {
    $error = 'Error loading notifications: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Campus Voice</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-bell me-2"></i>Notifications</h2>
            <form method="POST" class="d-inline">
                <button type="submit" name="mark_as_read" class="btn btn-outline-primary">
                    <i class="fas fa-check-double me-1"></i> Mark all as read
                </button>
            </form>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="list-group list-group-flush">
                <?php if (empty($notifications)): ?>
                    <div class="list-group-item text-center py-5">
                        <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                        <h5>No notifications yet</h5>
                        <p class="text-muted">When you get notifications, they'll appear here</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                        <div class="list-group-item list-group-item-action <?php echo $notification['read_at'] ? '' : 'bg-light'; ?>"
                           onclick="window.location.href = getNotificationUrl('<?php echo $notification['type']; ?>', <?php echo $notification['reference_id'] ?? '0'; ?>); markAsRead(<?php echo $notification['id']; ?>, this);">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1">
                                    <?php 
                                    if ($notification['type'] === 'feedback_response' && !empty($notification['feedback_response'])) {
                                        echo 'Response to: ' . htmlspecialchars($notification['feedback_title'] ?? 'Your feedback');
                                    } else {
                                        echo htmlspecialchars($notification['title']);
                                    }
                                    ?>
                                </h6>
                                <small class="text-muted"><?php echo timeAgo($notification['created_at']); ?></small>
                            </div>
                            
                            <?php if ($notification['type'] === 'feedback_response' && !empty($notification['feedback_response'])): ?>
                                <div class="alert alert-light p-2 mb-2">
                                    <div class="d-flex align-items-start">
                                        <div class="flex-shrink-0 me-2">
                                            <i class="fas fa-reply text-primary"></i>
                                        </div>
                                        <div>
                                            <small class="text-muted">
                                                <?php 
                                                $adminName = !empty($notification['admin_first_name']) 
                                                    ? htmlspecialchars($notification['admin_first_name'] . ' ' . ($notification['admin_last_name'] ?? '')) 
                                                    : 'Administrator';
                                                echo 'Response from ' . $adminName . ':';
                                                ?>
                                            </small>
                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($notification['feedback_response'])); ?></p>
                                            <small class="text-muted"><?php echo timeAgo($notification['response_date']); ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <p class="mb-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                            <?php endif; ?>
                            
                            <?php if (!$notification['read_at']): ?>
                                <span class="badge bg-primary">New</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to determine URL based on notification type
        function getNotificationUrl(type, referenceId) {
            switch(type) {
                case 'feedback':
                    return 'view-feedback.php?id=' + referenceId;
                case 'poll':
                    return 'polls.php';
                case 'comment':
                    return 'view-feedback.php?id=' + referenceId + '#comment-' + referenceId;
                default:
                    return '#';
            }
        }
        
        // Function to mark notification as read
        function markAsRead(notificationId, element) {
            fetch('api/notifications/read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'id=' + notificationId
            });
            
            // Update UI
            element.classList.remove('bg-light');
            const badge = element.querySelector('.badge');
            if (badge) {
                badge.remove();
            }
            
            // Update notification count
            const badge = document.getElementById('notification-badge');
            if (badge) {
                const count = parseInt(badge.textContent) - 1;
                if (count > 0) {
                    badge.textContent = count;
                } else {
                    badge.classList.add('d-none');
                }
            }
        }
        // Auto-refresh notifications every 30 seconds
        setInterval(function() {
            fetch('api/notifications/count.php')
                .then(response => response.json())
                .then(data => {
                    const badge = document.getElementById('notification-badge');
                    if (data.unread > 0) {
                        badge.textContent = data.unread;
                        badge.classList.remove('d-none');
                    } else {
                        badge.classList.add('d-none');
                    }
                });
        }, 30000);
    </script>
</body>
</html>
