<?php
// Define constant to prevent direct access
define('IN_CAMPUS_VOICE', true);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get feedback ID from POST data
$feedbackId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$feedbackId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid feedback ID']);
    exit;
}

try {
    $db = getDB();
    
    // Update feedback status to 'rejected' and set flagged status
    $stmt = $db->prepare("
        UPDATE feedback 
        SET status = 'rejected', 
            is_flagged = 1,
            flagged_at = NOW(),
            flagged_by = :admin_id
        WHERE id = :id
    ");
    
    $stmt->execute([
        ':id' => $feedbackId,
        ':admin_id' => $_SESSION['user_id']
    ]);
    
    if ($stmt->rowCount() > 0) {
        // Log the action
        $logStmt = $db->prepare("
            INSERT INTO admin_logs (admin_id, action, details) 
            VALUES (:admin_id, 'feedback_flagged', :details)
        ");
        
        $logStmt->execute([
            ':admin_id' => $_SESSION['user_id'],
            ':details' => 'Flagged feedback #' . $feedbackId . ' as inappropriate'
        ]);
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Feedback not found or already flagged']);
    }
    
} catch (PDOException $e) {
    error_log('Error flagging feedback: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while flagging the feedback'
    ]);
}
