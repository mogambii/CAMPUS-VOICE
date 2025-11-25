<?php
require_once 'includes/functions.php';

// Only allow this script to run in development environment
if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
    die('Access denied');
}

$db = getDB();

try {
    // Begin transaction
    $db->beginTransaction();

    // Create categories table if it doesn't exist
    $db->exec("
        CREATE TABLE IF NOT EXISTS `categories` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(100) NOT NULL,
            `description` text DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Specified categories
    $categories = [
        ['Academic', 'Course-related feedback, lectures, and academic concerns'],
        ['Food', 'Feedback about campus dining and food services'],
        ['Technical', 'IT issues, computer labs, and technical support'],
        ['Security', 'Campus safety and security concerns']
    ];

    // Insert categories
    $stmt = $db->prepare("INSERT IGNORE INTO `categories` (`name`, `description`) VALUES (?, ?)");
    
    $inserted = 0;
    foreach ($categories as $category) {
        $stmt->execute($category);
        $inserted += $stmt->rowCount();
    }

    // Commit transaction
    $db->commit();

    // Output success message
    echo "<h2>Setup Complete</h2>";
    echo "<p>Successfully added $inserted categories to the database.</p>";
    echo "<p><a href='submit-feedback.php'>Go to Feedback Form</a></p>";

} catch (Exception $e) {
    // Rollback transaction on error
    $db->rollBack();
    echo "<h2>Error</h2>";
    echo "<p>An error occurred: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>
