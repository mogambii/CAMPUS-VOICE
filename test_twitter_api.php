<?php
require_once __DIR__ . '/config/social_config.php';

// Test Twitter/X API Connection
function testTwitterAPI($hashtag) {
    $url = "https://api.twitter.com/2/tweets/search/recent?query=%23{$hashtag}&tweet.fields=created_at,author_id,attachments&expansions=author_id,attachments.media_keys&user.fields=name,username,profile_image_url&media.fields=preview_image_url,url";
    
    $headers = [
        "Authorization: Bearer " . TWITTER_BEARER_TOKEN,
        "User-Agent: CampusVoiceApp/1.0"
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_VERBOSE => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    
    return [
        'http_code' => $httpCode,
        'error' => $error,
        'response' => json_decode($response, true),
        'info' => $info
    ];
}

// Test the API
$result = testTwitterAPI('StrathmoreUniversity');

// Output the result
echo "<h2>Twitter API Test Results</h2>";
echo "<h3>HTTP Status: {$result['http_code']}</h3>";

if ($result['error']) {
    echo "<div style='color:red;'><strong>cURL Error:</strong> " . htmlspecialchars($result['error']) . "</div>";
}

echo "<h4>Response:</h4>";
echo "<pre>" . json_encode($result['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "</pre>";

echo "<h4>Request Info:</h4>";
echo "<pre>" . print_r($result['info'], true) . "</pre>";

// Check if cURL is installed and working
echo "<h4>cURL Info:</h4>";
echo "<pre>";
if (function_exists('curl_version')) {
    $curl_version = curl_version();
    echo "cURL is enabled. Version: " . $curl_version['version'] . "\n";
    echo "SSL Version: " . $curl_version['ssl_version'] . "\n";
    echo "SSL Version Number: " . $curl_version['ssl_version_number'] . "\n";
} else {
    echo "cURL is NOT enabled on this server.\n";
}
echo "</pre>";

// Check if allow_url_fopen is enabled
echo "<h4>PHP Configuration:</h4>";
echo "<pre>";
echo "allow_url_fopen: " . (ini_get('allow_url_fopen') ? 'On' : 'Off') . "\n";
echo "OpenSSL: " . (extension_loaded('openssl') ? 'Enabled' : 'Disabled') . "\n";
echo "</pre>";
?>
