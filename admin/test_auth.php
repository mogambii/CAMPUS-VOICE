<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once __DIR__ . '/../includes/functions.php';

// Test database connection
try {
    $db = getDB();
    echo "<p style='color: green;'>✅ Database connection successful!</p>";
    
    // Test admin user query
    $username = 'admin';
    $stmt = $db->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        echo "<h3>Admin User Found:</h3>";
        echo "<pre>" . print_r($admin, true) . "</pre>";
        
        // Test password verification
        $password = 'admin123'; // Try with the password you're using
        $isValid = password_verify($password, $admin['password']);
        
        echo "<h3>Password Test:</h3>";
        echo "Password to verify: " . htmlspecialchars($password) . "<br>";
        echo "Stored hash: " . htmlspecialchars($admin['password']) . "<br>";
        echo "Verification result: " . ($isValid ? '✅ Valid' : '❌ Invalid') . "<br>";
        
        if (!$isValid) {
            echo "<p style='color: red;'>The password you entered does not match the stored hash.</p>";
            echo "<p>Try hashing a new password and updating it in the database:</p>";
            $newPassword = 'admin123'; // Change this to your desired password
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            echo "<pre>UPDATE admins SET password = '" . $newHash . "' WHERE username = '$username';</pre>";
            echo "<p>Then try logging in with password: " . htmlspecialchars($newPassword) . "</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ No admin user found with username: " . htmlspecialchars($username) . "</p>";
        
        // Provide SQL to create admin user
        $password = 'admin123'; // Change this to your desired password
        $hash = password_hash($password, PASSWORD_DEFAULT);
        echo "<h3>To create an admin user, run this SQL:</h3>";
        echo "<pre>
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO admins (username, password, email) 
VALUES ('admin', '$hash', 'admin@example.com');
        </pre>";
        echo "<p>Then try logging in with username: admin and password: " . htmlspecialchars($password) . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    
    // Show database config (remove in production)
    $configFile = __DIR__ . '/../config/database.php';
    if (file_exists($configFile)) {
        echo "<h3>Database Configuration:</h3>";
        $config = include $configFile;
        echo "<pre>" . print_r($config, true) . "</pre>";
    } else {
        echo "<p>Could not find database configuration file at: " . htmlspecialchars($configFile) . "</p>";
    }
}
?>

<h3>Login Test Form</h3>
<form method="post" action="login.php">
    <div>
        <label>Username: <input type="text" name="username" value="admin"></label>
    </div>
    <div>
        <label>Password: <input type="password" name="password"></label>
    </div>
    <div>
        <button type="submit">Test Login</button>
    </div>
</form>
