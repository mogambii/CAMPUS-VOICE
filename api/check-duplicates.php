<?php
// Set higher memory limit for this script
ini_set('memory_limit', '512M');

// Enable garbage collection and set aggressive garbage collection
gc_enable();
gc_collect_cycles();

// Disable output buffering to prevent memory issues
if (ob_get_level()) ob_end_clean();
/**
 * API Endpoint: Check for Duplicate Feedback
 * 
 * Accepts POST requests with feedback text and returns similar existing feedback
 * with admin responses.
 */

// ==================== ERROR HANDLING & LOGGING ====================
// Disable error display to prevent HTML output
ini_set('display_errors', 0);
error_reporting(0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/api_errors.log');

// Start output buffering
ob_start();

// Function to send JSON response and exit
function sendJsonResponse($data, $statusCode = 200) {
    // Clear any previous output
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
    
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

/**
 * Get database connection using existing function from includes/functions.php
 * 
 * @return PDO Database connection
 * @throws PDOException If connection fails
 */
function getDBConnection() {
    static $pdo = null;
    
    // Return existing connection if available
    if ($pdo !== null) {
        return $pdo;
    }
    
    try {
        // Database configuration
        $host = 'localhost';
        $dbname = 'campus_voice';
        $username = 'root';
        $password = '';
        $charset = 'utf8mb4';
        
        $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => true, // Enable emulated prepares for better performance
            PDO::ATTR_PERSISTENT         => false, // Disable persistent connections
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];
        
        $pdo = new PDO($dsn, $username, $password, $options);
        
        // Test the connection
        $pdo->query('SELECT 1');
        
        return $pdo;
    } catch (PDOException $e) {
        error_log('Database connection failed: ' . $e->getMessage());
        throw new Exception('Could not connect to the database. Please try again later.');
    }
}

// Add debug logging
function logDebug($message) {
    $logFile = __DIR__ . '/../logs/api_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Log request method and URI
logDebug("Request Method: " . $_SERVER['REQUEST_METHOD']);
logDebug("Request URI: " . $_SERVER['REQUEST_URI']);
logDebug("Request Headers: " . print_r(getallheaders(), true));

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
    header('Access-Control-Max-Age: 86400');
    header('Content-Length: 0');
    header('Content-Type: text/plain');
    exit(0);
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logDebug("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    sendJsonResponse([
        'success' => false,
        'message' => 'Error: Only POST requests are allowed',
        'request_method' => $_SERVER['REQUEST_METHOD']
    ], 405);
}

// Get and validate JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    sendJsonResponse([
        'success' => false,
        'message' => 'Invalid JSON: ' . json_last_error_msg()
    ], 400);
}

// Validate required fields
if (empty($input['description']) || strlen($input['description']) < 10) {
    sendJsonResponse([
        'success' => false,
        'message' => 'Error: Description must be at least 10 characters long',
        'data' => [
            'similar_feedback' => [],
            'count' => 0
        ]
    ], 400);
}

// Initialize response array
$response = [
    'success' => false,
    'message' => 'An unexpected error occurred',
    'data' => [
        'similar_feedback' => [],
        'count' => 0
    ]
];

// Include required files
$requiredFiles = [
    __DIR__ . '/../includes/functions.php',
    __DIR__ . '/../utils/text-similarity.php'
];

foreach ($requiredFiles as $file) {
    if (!file_exists($file)) {
        throw new Exception("Required file not found: " . basename($file));
    }
    require_once $file;
}

try {
    // Get database connection
    $db = getDBConnection();
    
    // Test the database connection
    $db->query('SELECT 1')->fetch();
    
    // Enable garbage collection
    gc_enable();
    
    // Get only necessary fields to reduce memory usage
    $query = "SELECT 
        f.id, 
        f.title, 
        f.description, 
        f.status,
        c.name as category_name
    FROM feedback f
    LEFT JOIN categories c ON f.category_id = c.id
    WHERE f.status != 'deleted' ";
    
    // Add category filter if provided
    if (!empty($input['category_id'])) {
        $query .= " AND f.category_id = :category_id";
    }
    
    $query .= " ORDER BY f.created_at DESC LIMIT 100"; // Limit to 100 most recent for performance
    
    try {
        $stmt = $db->prepare($query);
        
        if (!empty($input['category_id'])) {
            $stmt->bindParam(':category_id', $input['category_id'], PDO::PARAM_INT);
        }
        
        if (!$stmt->execute()) {
            throw new PDOException('Failed to execute query: ' . implode(' ', $stmt->errorInfo()));
        }
        
        // Fetch results in chunks to save memory
        $allFeedback = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $allFeedback[] = $row;
            
            // Clear variables to free memory
            unset($row);
            
            // Force garbage collection every 20 rows
            if (count($allFeedback) % 20 === 0) {
                gc_collect_cycles();
            }
        }
        
        // Free the result set
        $stmt->closeCursor();
        
    } catch (PDOException $e) {
        // Log the error
        error_log('Database query error: ' . $e->getMessage());
        throw $e;
    }
    
    // Process feedback in chunks to save memory
    $feedbackTexts = [];
    $feedbackMap = [];
    $chunkSize = 20; // Process 20 items at a time
    $totalItems = count($allFeedback);
    
    for ($i = 0; $i < $totalItems; $i += $chunkSize) {
        $chunk = array_slice($allFeedback, $i, $chunkSize);
        
        foreach ($chunk as $feedback) {
            $feedbackText = ($feedback['title'] ?? '') . ' ' . ($feedback['description'] ?? '');
            $feedbackTexts[] = $feedbackText;
            $feedbackMap[md5($feedbackText)] = [
                'id' => $feedback['id'] ?? null,
                'title' => $feedback['title'] ?? '',
                'category_name' => $feedback['category_name'] ?? '',
                'status' => $feedback['status'] ?? ''
            ];
            
            // Clear variables to free memory
            unset($feedbackText);
        }
        
        // Clear chunk from memory
        unset($chunk);
        
        // Force garbage collection after each chunk
        gc_collect_cycles();
    }
    
    // Find similar feedback with memory optimization
    $similarItems = [];
    
    // Process similarity in chunks to save memory
    $textChunks = array_chunk($feedbackTexts, 50, true);
    $similarItems = [];
    
    foreach ($textChunks as $chunk) {
        $chunkSimilar = TextSimilarity::findMostSimilar($input['description'], $chunk, 3, 0.3);
        $similarItems = array_merge($similarItems, $chunkSimilar);
        unset($chunk, $chunkSimilar);
        gc_collect_cycles();
    }
    
    // Sort by similarity score (highest first) and take top 3
    usort($similarItems, function($a, $b) {
        return $b['similarity'] <=> $a['similarity'];
    });
    $similarItems = array_slice($similarItems, 0, 3);
    
    // Prepare response with minimal required data
    $formattedItems = [];
    
    foreach ($similarItems as $item) {
        $feedbackKey = md5($item['text']);
        if (isset($feedbackMap[$feedbackKey])) {
            $feedback = $feedbackMap[$feedbackKey];
            
            // Get admin responses if any
            $responses = [];
            if (!empty($feedback['response_count'])) {
                try {
                    $responseStmt = $db->prepare(
                        "SELECT fr.response, fr.created_at, u.name as admin_name 
                         FROM feedback_responses fr 
                         JOIN users u ON fr.admin_id = u.id 
                         WHERE fr.feedback_id = :feedback_id 
                         ORDER BY fr.created_at DESC"
                    );
                    $responseStmt->execute([':feedback_id' => $feedback['id']]);
                    $responses = $responseStmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    // Log the error but don't fail the request
                    error_log('Error fetching responses: ' . $e->getMessage());
                }
            }
            
            $formattedItems[] = [
                'id' => $feedback['id'] ?? null,
                'title' => $feedback['title'] ?? 'Untitled',
                'description' => $feedback['description'] ?? '',
                'status' => $feedback['status'] ?? 'pending',
                'category' => $feedback['category_name'] ?? 'Uncategorized',
                'submitted_by' => $feedback['user_name'] ?? 'Anonymous',
                'submitted_date' => $feedback['created_at'] ?? date('Y-m-d H:i:s'),
                'similarity_score' => round($item['score'] ?? 0, 2),
                'responses' => $responses
            ];
        }
    }
    
    // Sort by similarity score (highest first)
    usort($formattedItems, function($a, $b) {
        return $b['similarity_score'] <=> $a['similarity_score'];
    });
    
    // Send success response
    sendJsonResponse([
        'success' => true,
        'message' => count($formattedItems) > 0 
            ? 'Found ' . count($formattedItems) . ' similar feedback items' 
            : 'No similar feedback found',
        'data' => [
            'similar_feedback' => $formattedItems,
            'count' => count($formattedItems)
        ]
    ]);
    
} catch (Exception $e) {
    // Log the error
    error_log('API Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    
    // Send error response
    sendJsonResponse([
        'success' => false,
        'message' => 'An error occurred while processing your request',
        'error' => $e->getMessage()
    ], 500);
}
