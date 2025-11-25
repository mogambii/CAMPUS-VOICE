<?php
// Load database configuration
require_once __DIR__ . '/../includes/config.php';

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
    
    // Get all migration files
    $migrationFiles = glob(__DIR__ . '/migrations/*.sql');
    sort($migrationFiles);
    
    // Create migrations table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS `migrations` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `migration` VARCHAR(255) NOT NULL,
        `batch` INT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    // Get the batch number for new migrations
    $batch = $pdo->query("SELECT IFNULL(MAX(batch), 0) + 1 as next_batch FROM migrations")->fetch()['next_batch'];
    
    $migrationsRun = 0;
    
    foreach ($migrationFiles as $file) {
        $migrationName = basename($file);
        
        // Check if migration was already run
        $stmt = $pdo->prepare("SELECT id FROM migrations WHERE migration = ?");
        $stmt->execute([$migrationName]);
        
        if (!$stmt->fetch()) {
            // Read and execute the SQL file
            $sql = file_get_contents($file);
            $pdo->exec($sql);
            
            // Record the migration
            $stmt = $pdo->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)");
            $stmt->execute([$migrationName, $batch]);
            
            echo "Ran migration: " . $migrationName . "\n";
            $migrationsRun++;
        }
    }
    
    $pdo->commit();
    
    if ($migrationsRun > 0) {
        echo "\nSuccessfully ran $migrationsRun migration(s).\n";
    } else {
        echo "No new migrations to run.\n";
    }
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Migration failed: " . $e->getMessage() . "\n");
}

echo "Database migrations completed successfully!\n";
