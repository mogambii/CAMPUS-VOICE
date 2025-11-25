<?php
// Enable full error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

header('Content-Type: text/plain');
echo "=== Test API Endpoint ===\n\n";

// Test 1: Check if required files exist
$required_files = [
    'functions.php' => __DIR__ . '/includes/functions.php',
    'text-similarity.php' => __DIR__ . '/utils/text-similarity.php',
    'check-duplicates.php' => __DIR__ . '/api/check-duplicates.php'
];

echo "Checking required files...\n";
foreach ($required_files as $name => $path) {
    echo "- $name: " . (file_exists($path) ? '✅ Found' : '❌ Missing') . "\n";
}
echo "\n";

// Test 2: Test database connection
try {
    echo "Testing database connection...\n";
    $db = new PDO(
        'mysql:host=localhost;dbname=campus_voice;charset=utf8mb4',
        'root',
        '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => true,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false
        ]
    );
    echo "✅ Database connection successful!\n";
    
    // Test if required tables exist
    $tables = ['feedback', 'categories', 'users', 'feedback_responses'];
    foreach ($tables as $table) {
        try {
            $db->query("SELECT 1 FROM `$table` LIMIT 1");
            echo "- Table '$table': ✅ Exists\n";
        } catch (PDOException $e) {
            echo "- Table '$table': ❌ Missing or error: " . $e->getMessage() . "\n";
        }
    }
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 3: Test API endpoint
echo "Testing API endpoint...\n";
// Use the correct path without URL encoding the space in the path
$apiPath = '/CAMPUS VOICE/api/check-duplicates.php';
$url = 'http://' . $_SERVER['HTTP_HOST'] . $apiPath;
$data = json_encode(['description' => 'This is a test feedback message with more than 10 characters']);

echo "URL: $url\n";
echo "Request: $data\n\n";

// Initialize cURL session
$ch = curl_init($url);

// Set cURL options
$options = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $data,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        'Content-Length: ' . strlen($data)
    ],
    CURLOPT_HEADER => true,
    CURLOPT_VERBOSE => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0
];

curl_setopt_array($ch, $options);

// Execute the request
$response = curl_exec($ch);

// Check for cURL errors
if (curl_errno($ch)) {
    echo 'cURL Error: ' . curl_error($ch) . "\n\n";
}

// Get HTTP status code and response
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);

// Close cURL session
curl_close($ch);

echo "Response (Status: $httpCode):\n";
echo "Headers:\n$headers\n";
echo "Body:\n$body\n";

// Check for JSON validity
$json = json_decode($body);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "\n⚠️ Response is not valid JSON: " . json_last_error_msg() . "\n";
    echo "Raw response (first 500 chars):\n" . substr($body, 0, 500) . "\n";
}

echo "\n=== Test Complete ===\n";
