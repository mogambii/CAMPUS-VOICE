<?php
// AI Duplicate Detection API
header('Content-Type: application/json');

// Load core functions and configuration (for SIMILARITY_THRESHOLD, etc.)
if (!defined('IN_CAMPUS_VOICE')) {
    define('IN_CAMPUS_VOICE', true);
}
require_once '../includes/functions.php';
require_once '../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$title = $input['title'] ?? '';
$description = $input['description'] ?? '';

if (empty($title) || empty($description)) {
    echo json_encode(['success' => false, 'message' => 'Title and description are required']);
    exit();
}

$db = getDB();

// Simple text-based similarity check using MySQL FULLTEXT search
// For production, you would integrate with a proper NLP API or library
$search_query = $title . ' ' . $description;

$stmt = $db->prepare("
    SELECT 
        f.id,
        f.title,
        f.description,
        f.created_at,
        MATCH(f.title, f.description) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
    FROM feedback f
    WHERE MATCH(f.title, f.description) AGAINST(? IN NATURAL LANGUAGE MODE)
    AND f.status != 'rejected'
    HAVING relevance > 0.5
    ORDER BY relevance DESC
    LIMIT 5
");

$stmt->execute([$search_query, $search_query]);
$similar_posts = $stmt->fetchAll();

// Calculate similarity score using Levenshtein distance for better accuracy
$filtered_posts = [];
foreach ($similar_posts as $post) {
    $title_similarity = 1 - (levenshtein(strtolower($title), strtolower($post['title'])) / max(strlen($title), strlen($post['title'])));
    $desc_similarity = similar_text(strtolower($description), strtolower($post['description']), $percent);
    
    $overall_similarity = ($title_similarity * 0.6) + (($percent / 100) * 0.4);
    
    if ($overall_similarity >= SIMILARITY_THRESHOLD) {
        $post['similarity_score'] = round($overall_similarity * 100, 2);

        // Fetch any admin responses for this feedback so the bot can surface them
        try {
            $respStmt = $db->prepare("SELECT fr.response, fr.created_at, a.username AS admin_name 
                                      FROM feedback_responses fr 
                                      LEFT JOIN admins a ON fr.admin_id = a.id 
                                      WHERE fr.feedback_id = ? 
                                      ORDER BY fr.created_at ASC");
            $respStmt->execute([$post['id']]);
            $post['responses'] = $respStmt->fetchAll();
        } catch (PDOException $e) {
            error_log('Error fetching feedback responses in check_duplicate: ' . $e->getMessage());
            $post['responses'] = [];
        }

        $filtered_posts[] = $post;
    }
}

echo json_encode([
    'success' => true,
    'isDuplicate' => !empty($filtered_posts),
    'similarPosts' => $filtered_posts,
    'count' => count($filtered_posts)
]);
?>
