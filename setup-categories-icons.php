<?php
require_once 'includes/functions.php';

try {
    $db = getDB();
    
    // Add icon column to categories table if it doesn't exist
    $db->exec("ALTER TABLE categories ADD COLUMN IF NOT EXISTS icon VARCHAR(50) DEFAULT 'fa-folder' AFTER name");
    
    // Set default icons for existing categories
    $icons = [
        'Academic' => 'fa-graduation-cap',
        'Facilities' => 'fa-building',
        'Security' => 'fa-shield-alt',
        'Administration' => 'fa-user-tie',
        'Technical' => 'fa-laptop-code',
        'Food' => 'fa-utensils'
    ];
    
    $update = $db->prepare("UPDATE categories SET icon = ? WHERE name = ?");
    foreach ($icons as $name => $icon) {
        $update->execute([$icon, $name]);
    }
    
    echo "Categories updated successfully with icons!";
} catch (PDOException $e) {
    die("Error updating categories: " . $e->getMessage());
}
?>
