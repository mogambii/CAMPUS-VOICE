<?php
session_start();

echo "<h1>Login Flow Test</h1>";

// Clear session
session_destroy();
session_start();

echo "<h2>Testing Admin Login Flow</h2>";

// Simulate admin login
$_SESSION['user_id'] = 1;
$_SESSION['first_name'] = 'Admin';
$_SESSION['last_name'] = 'User';
$_SESSION['email'] = 'admin@campusvoice.edu';
$_SESSION['role'] = 'admin';

echo "<p>Admin session set:</p>";
echo "<ul>";
echo "<li>User ID: " . $_SESSION['user_id'] . "</li>";
echo "<li>Name: " . $_SESSION['first_name'] . " " . $_SESSION['last_name'] . "</li>";
echo "<li>Email: " . $_SESSION['email'] . "</li>";
echo "<li>Role: " . $_SESSION['role'] . "</li>";
echo "</ul>";

// Test redirect logic
echo "<h3>Redirect Logic Test:</h3>";
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        echo "<p style='color: green;'>✓ Would redirect to: admin/index.php</p>";
    } else {
        echo "<p style='color: blue;'>✓ Would redirect to: dashboard.php</p>";
    }
}

// Clear session for student test
session_destroy();
session_start();

echo "<h2>Testing Student Login Flow</h2>";

// Simulate student login
$_SESSION['user_id'] = 2;
$_SESSION['first_name'] = 'Student';
$_SESSION['last_name'] = 'User';
$_SESSION['email'] = 'student@campus.edu';
$_SESSION['role'] = 'student';

echo "<p>Student session set:</p>";
echo "<ul>";
echo "<li>User ID: " . $_SESSION['user_id'] . "</li>";
echo "<li>Name: " . $_SESSION['first_name'] . " " . $_SESSION['last_name'] . "</li>";
echo "<li>Email: " . $_SESSION['email'] . "</li>";
echo "<li>Role: " . $_SESSION['role'] . "</li>";
echo "</ul>";

// Test redirect logic
echo "<h3>Redirect Logic Test:</h3>";
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        echo "<p style='color: green;'>✓ Would redirect to: admin/index.php</p>";
    } else {
        echo "<p style='color: blue;'>✓ Would redirect to: dashboard.php</p>";
    }
}

echo "<h2>Test Complete</h2>";
echo "<p><a href='login.php'>Go to Login Page</a></p>";
echo "<p><a href='admin/index.php'>Go to Admin Dashboard</a></p>";
echo "<p><a href='dashboard.php'>Go to Student Dashboard</a></p>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 20px auto;
    padding: 20px;
}
</style>
