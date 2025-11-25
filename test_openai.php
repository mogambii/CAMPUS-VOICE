<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/ai_utils.php';

// Set error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if cURL is available
if (!function_exists('curl_init')) {
    die('cURL is not installed. This script requires cURL.');
}

try {
    // Test database connection first
    $db = getDB();
    if (!$db) {
        throw new Exception('Failed to connect to database');
    }
    echo "✅ Database connection successful!<br><br>";
    
    // Test OpenAI API
    $ai = new AIDuplicateDetector($db);
    
    // Test with sample text
    $testText = "The Wi-Fi in the library is very slow and keeps disconnecting.";
    
    echo "<strong>Testing OpenAI API with text:</strong><br>";
    echo htmlspecialchars($testText) . "<br><br>";
    
    // Test embedding generation
    echo "<strong>Generating embedding...</strong><br>";
    $startTime = microtime(true);
    $embedding = $ai->generateEmbedding($testText);
    $endTime = microtime(true);
    
    if (!empty($embedding)) {
        echo "✅ Success! Generated embedding with " . count($embedding) . " dimensions.<br>";
        echo "⏱️ Took " . number_format(($endTime - $startTime) * 1000, 2) . " ms<br><br>";
        
        // Test storing the embedding
        echo "<strong>Testing embedding storage...</strong><br>";
        $testFeedbackId = 1; // Using ID 1 for testing, should exist in your database
        
        if ($ai->storeEmbedding($testFeedbackId, $embedding)) {
            echo "✅ Successfully stored embedding for feedback #$testFeedbackId<br>";
            
            // Test retrieving the embedding
            $storedEmbedding = $ai->getStoredEmbedding($testFeedbackId);
            if ($storedEmbedding) {
                $storedArray = json_decode($storedEmbedding, true);
                echo "✅ Successfully retrieved embedding with " . count($storedArray) . " dimensions<br>";
            } else {
                echo "❌ Failed to retrieve stored embedding<br>";
            }
        } else {
            echo "❌ Failed to store embedding. Check database permissions.<br>";
        }
    } else {
        echo "❌ Failed to generate embedding. Check your OpenAI API key and internet connection.<br>";
    }
    
    // Test duplicate detection
    echo "<br><strong>Testing duplicate detection...</strong><br>";
    $similarFeedback = $ai->findSimilarFeedback($testText, 1, 3); // Category ID 1
    
    if (!empty($similarFeedback)) {
        echo "✅ Found " . count($similarFeedback) . " similar feedback items:<br>";
        foreach ($similarFeedback as $feedback) {
            $similarity = isset($feedback['similarity']) ? number_format($feedback['similarity'] * 100, 1) . '%' : 'N/A';
            echo "- #{$feedback['id']}: " . htmlspecialchars(substr($feedback['description'], 0, 50)) . "... (Similarity: $similarity)<br>";
        }
    } else {
        echo "ℹ️ No similar feedback found. This is normal if you don't have similar feedback in the database.<br>";
    }
    
} catch (Exception $e) {
    echo "<div style='color: red;'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</div><br>";
    
    // Show additional debugging info
    echo "<strong>Debug Info:</strong><br>";
    echo "PHP Version: " . phpversion() . "<br>";
    echo "cURL Enabled: " . (function_exists('curl_version') ? 'Yes' : 'No') . "<br>";
    
    if (function_exists('curl_version')) {
        $curlVersion = curl_version();
        echo "cURL Version: " . ($curlVersion['version'] ?? 'Unknown') . "<br>";
        echo "SSL Version: " . ($curlVersion['ssl_version'] ?? 'Not available') . "<br>";
    }
    
    echo "OpenSSL: " . (extension_loaded('openssl') ? 'Enabled' : 'Disabled') . "<br>";
    echo "Allow URL Fopen: " . (ini_get('allow_url_fopen') ? 'On' : 'Off') . "<br>";
}

// Show current configuration
if (defined('OPENAI_API_KEY')) {
    $keyPreview = substr(OPENAI_API_KEY, 0, 5) . '...' . substr(OPENAI_API_KEY, -4);
    echo "<br><strong>Current Configuration:</strong><br>";
    echo "OpenAI API Key: $keyPreview<br>";
    echo "OpenAI Model: " . (defined('OPENAI_EMBEDDING_MODEL') ? OPENAI_EMBEDDING_MODEL : 'Not set') . "<br>";
}
?>

<br><br>
<strong>Troubleshooting Tips:</strong>
<ol>
    <li>Make sure your OpenAI API key is valid and has sufficient credits</li>
    <li>Check that your server can make outbound HTTPS connections (port 443)</li>
    <li>Verify that the cURL and OpenSSL PHP extensions are enabled</li>
    <li>Check your PHP error logs for more detailed error messages</li>
    <li>Try a different API key if available</li>
</ol>
