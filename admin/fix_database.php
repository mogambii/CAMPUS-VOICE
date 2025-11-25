<?php
// This script will create the missing database tables

// Include database configuration
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Create database connection
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Create feedback table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS `feedback` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT,
        `category_id` INT,
        `status` ENUM('pending', 'in_progress', 'resolved') DEFAULT 'pending',
        `priority` ENUM('low', 'medium', 'high') DEFAULT 'medium',
        `is_anonymous` BOOLEAN DEFAULT FALSE,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FULLTEXT (`title`, `description`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    // Create feedback_duplicates table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `feedback_duplicates` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `feedback_id` INT NOT NULL,
        `duplicate_of` INT NOT NULL,
        `similarity_score` FLOAT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`feedback_id`) REFERENCES `feedback`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`duplicate_of`) REFERENCES `feedback`(`id`) ON DELETE CASCADE,
        UNIQUE KEY `unique_duplicate` (`feedback_id`, `duplicate_of`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    // Create categories table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `categories` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `description` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    // Create polls table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `polls` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `question` TEXT NOT NULL,
        `options` JSON NOT NULL,
        `end_date` DATETIME NOT NULL,
        `is_public` BOOLEAN DEFAULT TRUE,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    // Create poll_responses table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `poll_responses` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `poll_id` INT NOT NULL,
        `user_id` INT NOT NULL,
        `selected_option` INT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`poll_id`) REFERENCES `polls`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        UNIQUE KEY `unique_poll_response` (`poll_id`, `user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    // Create surveys table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `surveys` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT,
        `questions` JSON NOT NULL,
        `end_date` DATETIME,
        `is_public` BOOLEAN DEFAULT TRUE,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    // Create survey_responses table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `survey_responses` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `survey_id` INT NOT NULL,
        `user_id` INT NOT NULL,
        `responses` JSON NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`survey_id`) REFERENCES `surveys`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    // Insert default categories if not exist
    $pdo->exec("INSERT IGNORE INTO `categories` (`name`, `description`) VALUES
    ('Academic', 'Issues related to academic programs, courses, and faculty'),
    ('Facilities', 'Concerns about campus buildings, classrooms, and amenities'),
    ('Administration', 'Feedback about administrative processes and services'),
    ('Student Life', 'Activities, clubs, and student organizations'),
    ('Technology', 'IT services, WiFi, and technical support'),
    ('Safety', 'Campus security and emergency services'),
    ('Other', 'Any other feedback or suggestions')");
    
    // Commit the transaction
    $pdo->commit();
    
    echo "Database tables created successfully!\n";
    
} catch (PDOException $e) {
    // Roll back the transaction if something failed
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Error: " . $e->getMessage() . "\n");
}

echo "You can now access the dashboard without errors. <a href='dashboard.php'>Go to Dashboard</a>";
