<?php
session_start();

echo "<h1>Direct Redirect Test</h1>";

// Test 1: Admin redirect
echo "<h2>Test 1: Admin Redirect</h2>";
session_destroy();
session_start();

$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

echo "<p>Session role set to: 'admin'</p>";
echo "<p>Redirect logic: ";

if ($_SESSION['role'] === 'admin') {
    $redirect = 'admin/index.php';
    echo "<span style='color: green;'>✓ Admin detected - would redirect to: $redirect</span>";
} else {
    $redirect = 'dashboard.php';
    echo "<span style='color: red;'>✗ Admin NOT detected - would redirect to: $redirect</span>";
}

// Test 2: Student redirect
echo "<h2>Test 2: Student Redirect</h2>";
session_destroy();
session_start();

$_SESSION['user_id'] = 2;
$_SESSION['role'] = 'student';

echo "<p>Session role set to: 'student'</p>";
echo "<p>Redirect logic: ";

if ($_SESSION['role'] === 'admin') {
    $redirect = 'admin/index.php';
    echo "<span style='color: red;'>✗ Admin detected - would redirect to: $redirect</span>";
} else {
    $redirect = 'dashboard.php';
    echo "<span style='color: green;'>✓ Admin NOT detected - would redirect to: $redirect</span>";
}

// Test 3: Case sensitivity test
echo "<h2>Test 3: Case Sensitivity Test</h2>";
session_destroy();
session_start();

$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'ADMIN'; // Uppercase

echo "<p>Session role set to: 'ADMIN' (uppercase)</p>";
echo "<p>Redirect logic: ";

if ($_SESSION['role'] === 'admin') {
    $redirect = 'admin/index.php';
    echo "<span style='color: red;'>✗ Admin detected (case mismatch) - would redirect to: $redirect</span>";
} else {
    $redirect = 'dashboard.php';
    echo "<span style='color: green;'>✓ Admin NOT detected (case mismatch) - would redirect to: $redirect</span>";
}

echo "<h2>Database Role Check</h2>";

// Check actual database roles
require_once 'includes/functions.php';
$db = getDB();

try {
    $stmt = $db->query("SELECT email, role FROM users ORDER BY role, email");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Email</th><th>Role (exact)</th><th>Should redirect to</th></tr>";
    
    foreach ($users as $user) {
        $role = $user['role'];
        $expected_redirect = ($role === 'admin') ? 'admin/index.php' : 'dashboard.php';
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($user['email']) . "</td>";
        echo "<td style='font-family: monospace;'>" . htmlspecialchars($role) . "</td>";
        echo "<td style='color: " . (($role === 'admin') ? 'green' : 'blue') . ";'>$expected_redirect</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h2>Fix Database Roles</h2>";
echo "<p>If any admin roles have wrong case or values, run this SQL:</p>";
echo "<pre style='background: #f5f5f5; padding: 10px;'>";
echo "-- Fix admin role (case sensitive)
UPDATE users SET role = 'admin' WHERE email = 'admin@campusvoice.edu';

-- Fix any other admin roles
UPDATE users SET role = 'admin' WHERE role = 'ADMIN' OR role = 'Admin';

-- Fix student roles
UPDATE users SET role = 'student' WHERE role NOT IN ('admin', 'student');";
echo "</pre>";

echo "<p><a href='login.php'>Test actual login</a></p>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 900px;
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
pre {
    background-color: #f5f5f5;
    padding: 10px;
    border-radius: 5px;
}
</style>
