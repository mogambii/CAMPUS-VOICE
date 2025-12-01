<?php
// config.php - UPDATED with correct service name

// Database Configuration for Coolify
define('DB_HOST', 'mysql-database-docswcw4cs0gcckk40c04cww');  // ← THIS IS THE CORRECT SERVICE NAME
define('DB_USER', 'root');
define('DB_PASS', 'lUlceU2YvoQTQ6dfmPAFPJqSJKETMSRV3ZsqBySgoAzTL4k4sRNWbRiu6hZhsjoZ');
define('DB_NAME', 'default');  // ← Uses the "Initial Database" value from your settings
define('DB_PORT', '3306');

// OR if you want to use the normal user:
// define('DB_USER', 'mysql');
// define('DB_PASS', '*************'); // The Normal User Password from your settings

// Test connection
function test_connection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    
    if ($conn->connect_error) {
        return "Connection failed: " . $conn->connect_error;
    }
    
    return "✅ Connected successfully to database: " . DB_NAME;
}

// Usage in your code
$connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
?>