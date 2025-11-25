<?php
header('Content-Type: application/json');
require_once '../../includes/functions.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user = getCurrentUser();
$db = getDB();

try {
    $stmt = $db->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND read_at IS NULL");
    $stmt->execute([$user['id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'unread' => (int)$result['unread']
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error fetching notification count'
    ]);
}
