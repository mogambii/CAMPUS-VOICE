<?php
// Submit Feedback API
header('Content-Type: application/json');
require_once '../includes/functions.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$user = getCurrentUser();
$db = getDB();

$title = sanitize($_POST['title'] ?? '');
$description = sanitize($_POST['description'] ?? '');
$category_id = intval($_POST['category_id'] ?? 0);
$location = sanitize($_POST['location'] ?? '');
$priority = sanitize($_POST['priority'] ?? 'medium');
$is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;

// Validation
if (empty($title) || empty($description) || $category_id === 0) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
    exit();
}

// Handle file uploads if any
$attachments = [];
if (isset($_FILES['attachments'])) {
    foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
        if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
            $file = [
                'name' => $_FILES['attachments']['name'][$key],
                'tmp_name' => $tmp_name,
                'size' => $_FILES['attachments']['size'][$key],
                'error' => $_FILES['attachments']['error'][$key]
            ];
            
            $upload_result = uploadFile($file);
            if ($upload_result['success']) {
                $attachments[] = $upload_result['filename'];
            }
        }
    }
}

$attachments_json = !empty($attachments) ? json_encode($attachments) : null;

// Insert feedback
$stmt = $db->prepare("
    INSERT INTO feedback (user_id, category_id, title, description, location, priority, is_anonymous, attachments)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");

try {
    $stmt->execute([$user['id'], $category_id, $title, $description, $location, $priority, $is_anonymous, $attachments_json]);
    $feedback_id = $db->lastInsertId();
    
    logActivity($user['id'], 'submit_feedback', 'feedback', $feedback_id);
    
    echo json_encode([
        'success' => true,
        'message' => 'Feedback submitted successfully',
        'feedback_id' => $feedback_id
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to submit feedback']);
}
?>
