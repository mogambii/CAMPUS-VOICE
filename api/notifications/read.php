<?php
header('Content-Type: application/json');
require_once '../../includes/functions.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user = getCurrentUser();
$db = getDB();

// Get notification ID from POST data
$notificationId = $_POST['id'] ?? null;

if (!$notificationId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Notification ID is required']);
    exit;
}

try {
    // Verify the notification belongs to the user
    $stmt = $db->prepare("SELECT id FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->execute([$notificationId, $user['id']]);
    
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Notification not found']);
        exit;
    }
    
    // Mark as read
    $update = $db->prepare("UPDATE notifications SET read_at = NOW() WHERE id = ?");
    $update->execute([$notificationId]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error marking notification as read']);
}
