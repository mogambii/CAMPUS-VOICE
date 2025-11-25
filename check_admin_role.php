<?php
// Check admin role in database
require_once 'includes/functions.php';
$db = getDB();

echo "<h1>Admin Role Check</h1>";

try {
    // Check admin user specifically
    $stmt = $db->prepare("SELECT id, first_name, last_name, email, role FROM users WHERE email = ?");
    $stmt->execute(['admin@campusvoice.edu']);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        echo "<h2>Admin User Found:</h2>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><td>" . $admin['id'] . "</td></tr>";
        echo "<tr><th>Name</th><td>" . htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']) . "</td></tr>";
        echo "<tr><th>Email</th><td>" . htmlspecialchars($admin['email']) . "</td></tr>";
        echo "<tr><th>Role</th><td style='color: " . (($admin['role'] === 'admin') ? 'green' : 'red') . "; font-weight: bold;'>" . htmlspecialchars($admin['role']) . "</td></tr>";
        echo "</table>";
        
        if ($admin['role'] !== 'admin') {
            echo "<h3 style='color: red;'>⚠ PROBLEM: Admin role is not set correctly!</h3>";
            echo "<p>Current role: '" . htmlspecialchars($admin['role']) . "'</p>";
            echo "<p>Should be: 'admin'</p>";
            
            // Fix it
            $update_stmt = $db->prepare("UPDATE users SET role = 'admin' WHERE email = ?");
            $update_stmt->execute(['admin@campusvoice.edu']);
            echo "<p style='color: green;'>✓ Fixed: Updated admin role to 'admin'</p>";
        } else {
            echo "<h3 style='color: green;'>✓ Admin role is set correctly!</h3>";
        }
    } else {
        echo "<h3 style='color: red;'>⚠ Admin user not found!</h3>";
        
        // Create admin user
        $insert_stmt = $db->prepare("
            INSERT INTO users (first_name, last_name, email, password, role, email_verified) 
            VALUES ('Admin', 'User', 'admin@campusvoice.edu', ?, 'admin', TRUE)
        ");
        $hash = password_hash('password', PASSWORD_DEFAULT);
        $insert_stmt->execute([$hash]);
        echo "<p style='color: green;'>✓ Created admin user with email: admin@campusvoice.edu</p>";
        echo "<p>Password: password</p>";
    }
    
    // Check all users
    echo "<h2>All Users:</h2>";
    $stmt = $db->query("SELECT id, first_name, last_name, email, role FROM users ORDER BY role, email");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($users)) {
        echo "<p>No users found in database.</p>";
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . $user['id'] . "</td>";
            echo "<td>" . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . "</td>";
            echo "<td>" . htmlspecialchars($user['email']) . "</td>";
            echo "<td style='color: " . (($user['role'] === 'admin') ? 'green' : 'blue') . "; font-weight: bold;'>" . htmlspecialchars($user['role']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<p><a href='login.php'>Go to Login Page</a></p>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 20px auto;
    padding: 20px;
}
table {
    border-collapse: collapse;
    margin: 10px 0;
}
th {
    background-color: #f0f0f0;
    font-weight: bold;
}
</style>
