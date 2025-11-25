<?php
define('IN_CAMPUS_VOICE', true);
require_once 'includes/functions.php';
requireLogin();

$db = getDB();
$user = getCurrentUser();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vote'])) {
    $poll_id = (int)($_POST['poll_id'] ?? 0);
    $option_id = (int)($_POST['option_id'] ?? 0);
    
    try {
        if ($poll_id < 1 || $option_id < 1) {
            throw new Exception('Invalid poll or option selected.');
        }
        
        $db->beginTransaction();
        
        // Check if poll exists and is active
        $stmt = $db->prepare("
            SELECT 1 FROM polls 
            WHERE id = ? AND (end_date IS NULL OR end_date >= CURDATE())
        ");
        $stmt->execute([$poll_id]);
        if (!$stmt->fetch()) {
            throw new Exception('This poll is no longer active or does not exist.');
        }
        
        // Check if user already voted
        $stmt = $db->prepare("
            SELECT 1 FROM poll_votes 
            WHERE poll_id = ? AND user_id = ?
        ");
        $stmt->execute([$poll_id, $user['id']]);
        if ($stmt->fetch()) {
            throw new Exception('You have already voted in this poll.');
        }
        
        // Record the vote
        $stmt = $db->prepare("
            INSERT INTO poll_votes (poll_id, option_id, user_id, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$poll_id, $option_id, $user['id']]);
        
        $db->commit();
        $_SESSION['success'] = 'Your vote has been recorded!';
        header("Location: view-poll.php?id=$poll_id");
        exit;
        
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = $e->getMessage();
    }
}

// If we get here, there was an error or invalid request
header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'polls.php'));
exit;
