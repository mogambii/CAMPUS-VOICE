<?php
// Define constant to prevent direct access
define('IN_CAMPUS_VOICE', true);

// Include necessary files
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Check if user is logged in as admin
requireAdmin();

$db = getDB();

// Create feedback table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS `feedback` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `category_id` int(11) DEFAULT NULL,
    `subject` varchar(255) NOT NULL,
    `message` text NOT NULL,
    `status` enum('pending','in_progress','resolved','rejected') NOT NULL DEFAULT 'pending',
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `category_id` (`category_id`),
    CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `feedback_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// Create feedback_responses table
$sql2 = "CREATE TABLE IF NOT EXISTS `feedback_responses` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `feedback_id` int(11) NOT NULL,
    `admin_id` int(11) NOT NULL,
    `response` text NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `feedback_id` (`feedback_id`),
    KEY `admin_id` (`admin_id`),
    CONSTRAINT `feedback_responses_ibfk_1` FOREIGN KEY (`feedback_id`) REFERENCES `feedback` (`id`) ON DELETE CASCADE,
    CONSTRAINT `feedback_responses_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// Create admins table if it doesn't exist
$sql3 = "CREATE TABLE IF NOT EXISTS `admins` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `username` varchar(50) NOT NULL,
    `password` varchar(255) NOT NULL,
    `email` varchar(100) NOT NULL,
    `first_name` varchar(50) NOT NULL,
    `last_name` varchar(50) NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `last_login` datetime DEFAULT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `username` (`username`),
    UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// Add default admin user if not exists
$sql4 = "INSERT IGNORE INTO `admins` 
    (`username`, `password`, `email`, `first_name`, `last_name`) 
    VALUES 
    ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com', 'Admin', 'User')";

try {
    // Execute all SQL queries
    $db->exec($sql);
    echo "Table 'feedback' created successfully!<br>";
    
    $db->exec($sql2);
    echo "Table 'feedback_responses' created successfully!<br>";
    
    $db->exec($sql3);
    echo "Table 'admins' created successfully!<br>";
    
    $db->exec($sql4);
    echo "Default admin user created successfully!<br>";
    
    echo "<p>Feedback system setup completed successfully!</p>";
    echo "<p><a href='manage-feedback.php'>Go to Feedback Management</a></p>";
    
} catch (PDOException $e) {
    echo "Error setting up feedback system: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Setup Feedback System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header">
                <h4>Feedback System Setup</h4>
            </div>
            <div class="card-body">
                <p>The feedback system has been set up successfully.</p>
                <p>You can now <a href='manage-feedback.php'>manage feedback</a>.</p>
                <p><strong>Default Admin Login:</strong><br>
                Username: admin<br>
                Password: admin123</p>
                <div class="alert alert-warning">
                    <strong>Important:</strong> Delete this file after setup for security reasons.
                </div>
            </div>
        </div>
    </div>
</body>
</html>
