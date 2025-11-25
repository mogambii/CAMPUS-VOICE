<?php
// Define constant to prevent direct access
define('IN_CAMPUS_VOICE', true);

// Include necessary files
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Check if user is logged in as admin
requireAdmin();

$db = getDB();

// Create feedback_responses table
$sql = "CREATE TABLE IF NOT EXISTS `feedback_responses` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `feedback_id` int(11) NOT NULL,
    `admin_id` int(11) NOT NULL,
    `response` text NOT NULL,
    `created_at` datetime NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `feedback_id` (`feedback_id`),
    KEY `admin_id` (`admin_id`),
    CONSTRAINT `feedback_responses_ibfk_1` FOREIGN KEY (`feedback_id`) REFERENCES `feedback` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `feedback_responses_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

try {
    $db->exec($sql);
    echo "Table 'feedback_responses' created successfully!";
    
    // Add the response_count column to feedback table if it doesn't exist
    $db->exec("ALTER TABLE `feedback` ADD COLUMN IF NOT EXISTS `response_count` INT DEFAULT 0 AFTER `status`");
    
    echo "<br>Database structure updated successfully!";
    echo "<p><a href='manage-feedback.php'>Go back to Manage Feedback</a></p>";
    
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Create Feedback Responses Table</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header">
                <h4>Database Update</h4>
            </div>
            <div class="card-body">
                <p>The feedback_responses table has been created successfully.</p>
                <p>You can now <a href='manage-feedback.php'>go back to Manage Feedback</a>.</p>
            </div>
        </div>
    </div>
</body>
</html>
