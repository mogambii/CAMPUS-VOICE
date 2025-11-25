<?php
// Define constant to prevent direct access
define('IN_CAMPUS_VOICE', true);

// Include necessary files
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Check if user is logged in as admin
requireAdmin();

$db = getDB();
$success = [];
$errors = [];

// SQL to create the polls table
$createPollsTable = "
CREATE TABLE IF NOT EXISTS `polls` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('draft','active','upcoming','ended') NOT NULL DEFAULT 'draft',
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `allow_multiple` tinyint(1) NOT NULL DEFAULT 0,
  `show_results` tinyint(1) NOT NULL DEFAULT 1,
  `anonymous_voting` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `start_date` (`start_date`),
  KEY `end_date` (`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

// SQL to create the poll_options table
$createPollOptionsTable = "
CREATE TABLE IF NOT EXISTS `poll_options` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `poll_id` int(11) NOT NULL,
  `option_text` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `poll_id` (`poll_id`),
  CONSTRAINT `poll_options_ibfk_1` FOREIGN KEY (`poll_id`) REFERENCES `polls` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

// SQL to create the poll_votes table
$createPollVotesTable = "
CREATE TABLE IF NOT EXISTS `poll_votes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `poll_id` int(11) NOT NULL,
  `option_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `poll_user` (`poll_id`,`user_id`) USING BTREE,
  KEY `option_id` (`option_id`),
  KEY `ip_address` (`ip_address`),
  CONSTRAINT `poll_votes_ibfk_1` FOREIGN KEY (`poll_id`) REFERENCES `polls` (`id`) ON DELETE CASCADE,
  CONSTRAINT `poll_votes_ibfk_2` FOREIGN KEY (`option_id`) REFERENCES `poll_options` (`id`) ON DELETE CASCADE,
  CONSTRAINT `poll_votes_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

// Execute the SQL statements
try {
    // Create polls table
    $db->exec($createPollsTable);
    $success[] = "Polls table created successfully";
} catch (PDOException $e) {
    $errors[] = "Error creating polls table: " . $e->getMessage();
}

try {
    // Create poll_options table
    $db->exec($createPollOptionsTable);
    $success[] = "Poll options table created successfully";
} catch (PDOException $e) {
    $errors[] = "Error creating poll options table: " . $e->getMessage();
}

try {
    // Create poll_votes table
    $db->exec($createPollVotesTable);
    $success[] = "Poll votes table created successfully";
} catch (PDOException $e) {
    $errors[] = "Error creating poll votes table: " . $e->getMessage();
}

// Add a foreign key to the polls table for created_by
try {
    $db->exec("ALTER TABLE `polls` ADD CONSTRAINT `polls_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;");
    $success[] = "Added foreign key for created_by in polls table";
} catch (PDOException $e) {
    // Ignore if the foreign key already exists
    if (strpos($e->getMessage(), 'Duplicate foreign key constraint name') === false) {
        $errors[] = "Error adding foreign key to polls table: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Polls Tables - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Polls Tables Setup</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success">
                                <h5>Success!</h5>
                                <ul class="mb-0">
                                    <?php foreach ($success as $msg): ?>
                                        <li><?= $msg ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <h5>Errors:</h5>
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= $error ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-4">
                            <h5>Next Steps:</h5>
                            <ol>
                                <li>Delete this file after successful execution for security reasons</li>
                                <li>Navigate to <a href="polls.php">Polls Management</a> to create your first poll</li>
                            </ol>
                        </div>
                        
                        <div class="mt-4">
                            <a href="polls.php" class="btn btn-primary">
                                <i class="fas fa-arrow-right me-1"></i> Go to Polls Management
                            </a>
                            <a href="index.php" class="btn btn-outline-secondary ms-2">
                                <i class="fas fa-home me-1"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
