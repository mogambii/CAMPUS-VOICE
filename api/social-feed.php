<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/social_config.php';
require_once __DIR__ . '/../includes/SocialFeed.php';

// Get hashtag from query parameter or use default
$hashtag = isset($_GET['tag']) ? trim($_GET['tag'], '#') : 'campusvoice';

// Create new SocialFeed instance
try {
    $socialFeed = new SocialFeed();
    $feed = $socialFeed->getFeed($hashtag);
    
    echo json_encode([
        'status' => 'success',
        'data' => $feed
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
