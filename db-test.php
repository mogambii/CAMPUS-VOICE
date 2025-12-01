<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
define('DB_HOST', 'mysql-database-docswcw4cs0gcckk40c04cww');
define('DB_USER', 'root');
define('DB_PASS', 'lUlceU2YvoQTQ6dfmPAFPJqSJKETMSRV3ZsqBySgoAzTL4k4sRNWbRiu6hZhsjoZ');
define('DB_NAME', 'default');
define('DB_PORT', '5432');

echo "<h2>Database Connection Test</h2>";

// Test 1: Connect without selecting database
echo "<h3>1. Testing server connection...</h3>";
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, '', DB_PORT);
if ($conn->connect_error) {
    echo "❌ Failed: " . htmlspecialchars($conn->connect_error) . "<br>";
    echo "Trying port 5432 (from your mapping)...<br>";
    
    // Try port 5432 since that's what's mapped
    $conn2 = new mysqli(DB_HOST, DB_USER, DB_PASS, '', 5432);
    if ($conn2->connect_error) {
        echo "❌ Also failed on port 5432: " . htmlspecialchars($conn2->connect_error);
    } else {
        echo "✅ Connected on port 5432! Update DB_PORT to 5432";
    }
} else {
    echo "✅ Connected to MySQL server!<br>";
    
    // Show version
    echo "MySQL Version: " . $conn->server_version . "<br>";
    
    // Show databases
    echo "<h3>2. Available databases:</h3>";
    $result = $conn->query("SHOW DATABASES");
    while ($row = $result->fetch_array()) {
        echo $row[0] . "<br>";
    }
    
    // Try to use the default database
    echo "<h3>3. Selecting database '" . DB_NAME . "'...</h3>";
    if ($conn->select_db(DB_NAME)) {
        echo "✅ Database selected!<br>";
        
        // Show tables
        $result = $conn->query("SHOW TABLES");
        if ($result->num_rows > 0) {
            echo "Tables in database:<br>";
            while ($row = $result->fetch_array()) {
                echo "- " . $row[0] . "<br>";
            }
        } else {
            echo "No tables found. Database is empty.<br>";
        }
    } else {
        echo "❌ Cannot select database. Creating it...<br>";
        if ($conn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME)) {
            echo "✅ Database created!<br>";
            $conn->select_db(DB_NAME);
        } else {
            echo "❌ Failed to create database: " . htmlspecialchars($conn->error);
        }
    }
    
    $conn->close();
}
?>