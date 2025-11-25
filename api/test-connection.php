<?php
/**
 * Test Connection Endpoint
 * 
 * Simple endpoint to test API connectivity and database connection
 */

// ==================== CONFIGURATION ====================
// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Ensure logs directory exists
$logDir = __DIR__ . '/../logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

// Set error log path
ini_set('error_log', $logDir . '/api_test.log');

// ==================== HELPER FUNCTIONS ====================
function logTest($message, $data = []) {
    $logMessage = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if (!empty($data)) {
        $logMessage .= ' ' . json_encode($data, JSON_PRETTY_PRINT);
    }
    error_log($logMessage);
}

function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
    
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit();
}

// ==================== MAIN LOGIC ====================
try {
    // Log the test request
    logTest('Test connection request', [
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
        'uri' => $_SERVER['REQUEST_URI'] ?? 'UNKNOWN',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN'
    ]);

    // Handle preflight OPTIONS request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        sendJsonResponse(['success' => true, 'message' => 'Preflight check successful']);
    }

    // Initialize response
    $response = [
        'success' => true,
        'message' => 'API Test Endpoint',
        'data' => [
            'server' => [
                'php_version' => phpversion(),
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
                'request_uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
                'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
                'server_name' => $_SERVER['SERVER_NAME'] ?? 'N/A',
                'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'N/A',
                'script_filename' => __FILE__
            ],
            'database' => [
                'connected' => false,
                'error' => null,
                'tables' => []
            ],
            'environment' => [
                'os' => PHP_OS,
                'timezone' => date_default_timezone_get(),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time')
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ];

    // Test database connection if requested
    if (isset($_GET['test_db'])) {
        try {
            $dbConfig = [
                'host' => 'localhost',
                'dbname' => 'campus_voice', // Update with your database name
                'username' => 'root',       // Update with your database username
                'password' => ''            // Update with your database password
            ];

            // Try to connect to database
            $pdo = new PDO(
                "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8mb4",
                $dbConfig['username'],
                $dbConfig['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            $response['data']['database']['connected'] = true;
            
            // Try to get tables if connected
            try {
                $stmt = $pdo->query("SHOW TABLES");
                $tables = [];
                while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                    $tables[] = $row[0];
                }
                $response['data']['database']['tables'] = $tables;
            } catch (Exception $e) {
                $response['data']['database']['error'] = 'Could not fetch tables: ' . $e->getMessage();
            }
            
        } catch (PDOException $e) {
            $response['data']['database']['error'] = $e->getMessage();
            $response['success'] = false;
            $response['message'] = 'Database connection failed';
            logTest('Database connection failed', ['error' => $e->getMessage()]);
        }
    }

    // Test file permissions
    $response['data']['permissions'] = [
        'log_file_writable' => is_writable(ini_get('error_log')),
        'log_dir_writable' => is_writable($logDir),
        'log_file' => ini_get('error_log')
    ];

    // Test required PHP extensions
    $requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring'];
    $response['data']['extensions'] = [];
    foreach ($requiredExtensions as $ext) {
        $response['data']['extensions'][$ext] = [
            'loaded' => extension_loaded($ext),
            'version' => phpversion($ext) ?: 'Not loaded'
        ];
    }

    // Send successful response
    sendJsonResponse($response);

} catch (Exception $e) {
    // Handle any unexpected errors
    $errorData = [
        'success' => false,
        'message' => 'An unexpected error occurred',
        'error' => [
            'code' => $e->getCode(),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ];
    
    logTest('Unexpected error', $errorData);
    sendJsonResponse($errorData, 500);
}
