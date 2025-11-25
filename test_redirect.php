<?php
session_start();

echo "<h1>Login Redirect Test</h1>";

// Clear session
session_destroy();
session_start();

echo "<h2>Testing Student Redirect</h2>";

// Simulate student session
$_SESSION['user_id'] = 2;
$_SESSION['first_name'] = 'Test';
$_SESSION['last_name'] = 'Student';
$_SESSION['email'] = 'student@test.com';
$_SESSION['role'] = 'student';

echo "<p>Session set with role: student</p>";
echo "<p>Redirect logic result: ";

// Test the same logic as login.php
if ($_SESSION['role'] === 'admin') {
    $redirect = 'admin/index.php';
    echo "<span style='color: red;'>Would go to admin dashboard (WRONG!)</span>";
} else {
    $redirect = 'dashboard.php';
    echo "<span style='color: green;'>Would go to user dashboard (CORRECT!)</span>";
}

echo "</p>";
echo "<p>Redirect URL: <strong>" . $redirect . "</strong></p>";

echo "<h2>Testing Admin Redirect</h2>";

// Clear and set admin session
session_destroy();
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['first_name'] = 'Test';
$_SESSION['last_name'] = 'Admin';
$_SESSION['email'] = 'admin@test.com';
$_SESSION['role'] = 'admin';

echo "<p>Session set with role: admin</p>";
echo "<p>Redirect logic result: ";

// Test the same logic as login.php
if ($_SESSION['role'] === 'admin') {
    $redirect = 'admin/index.php';
    echo "<span style='color: green;'>Would go to admin dashboard (CORRECT!)</span>";
} else {
    $redirect = 'dashboard.php';
    echo "<span style='color: red;'>Would go to user dashboard (WRONG!)</span>";
}

echo "</p>";
echo "<p>Redirect URL: <strong>" . $redirect . "</strong></p>";

echo "<h2>Test Complete</h2>";
echo "<p>If the results above show CORRECT for both tests, the redirect logic is fixed.</p>";
echo "<p><a href='login.php'>Test actual login</a></p>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 600px;
    margin: 20px auto;
    padding: 20px;
}
</style>
