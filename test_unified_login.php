<?php
// Test script for unified login system
session_start();

// Include helper functions
require_once 'includes/functions.php';

// Get database connection
$db = getDB();

echo "<h1>Unified Login System Test</h1>";

// Test 1: Check database schema
echo "<h2>Test 1: Database Schema</h2>";
try {
    $stmt = $db->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $has_role_column = false;
    
    foreach ($columns as $column) {
        if ($column['Field'] === 'role') {
            $has_role_column = true;
            echo "<p style='color: green;'>✓ Role column exists: " . htmlspecialchars($column['Type']) . "</p>";
            break;
        }
    }
    
    if (!$has_role_column) {
        echo "<p style='color: red;'>✗ Role column not found in users table</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test 2: Check users and their roles
echo "<h2>Test 2: User Roles</h2>";
try {
    $stmt = $db->query("SELECT id, first_name, last_name, email, role FROM users ORDER BY role, email");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($users)) {
        echo "<p style='color: orange;'>⚠ No users found in database</p>";
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th></tr>";
        foreach ($users as $user) {
            $role_color = ($user['role'] === 'admin') ? 'green' : 'blue';
            echo "<tr>";
            echo "<td>" . htmlspecialchars($user['id']) . "</td>";
            echo "<td>" . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . "</td>";
            echo "<td>" . htmlspecialchars($user['email']) . "</td>";
            echo "<td style='color: $role_color; font-weight: bold;'>" . htmlspecialchars($user['role']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error fetching users: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test 3: Session simulation
echo "<h2>Test 3: Session Simulation</h2>";

// Clear any existing session
session_destroy();
session_start();

// Simulate admin login
$_SESSION['user_id'] = 1;
$_SESSION['first_name'] = 'Admin';
$_SESSION['last_name'] = 'User';
$_SESSION['email'] = 'admin@campusvoice.edu';
$_SESSION['role'] = 'admin';

echo "<h3>Admin Session Test</h3>";
echo "<p>Session variables set for admin user</p>";
echo "<p>isAdmin() function: " . (isAdmin() ? "<span style='color: green;'>✓ TRUE</span>" : "<span style='color: red;'>✗ FALSE</span>") . "</p>";

// Simulate regular user login
session_destroy();
session_start();

$_SESSION['user_id'] = 2;
$_SESSION['first_name'] = 'Regular';
$_SESSION['last_name'] = 'User';
$_SESSION['email'] = 'user@example.com';
$_SESSION['role'] = 'user';

echo "<h3>Regular User Session Test</h3>";
echo "<p>Session variables set for regular user</p>";
echo "<p>isAdmin() function: " . (isAdmin() ? "<span style='color: red;'>✗ TRUE (should be FALSE)</span>" : "<span style='color: green;'>✓ FALSE</span>") . "</p>";

// Test 4: Check dashboard.php accessibility
echo "<h2>Test 4: Dashboard Accessibility</h2>";
echo "<p><a href='dashboard.php' target='_blank'>Open Dashboard (Admin Session)</a></p>";
echo "<p><small>Note: This will open with the last simulated session (regular user). Refresh the page after switching sessions.</small></p>";

// Clear session
session_destroy();

echo "<h2>Test Complete</h2>";
echo "<p>If all tests show green checkmarks, the unified login system is working correctly.</p>";
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
