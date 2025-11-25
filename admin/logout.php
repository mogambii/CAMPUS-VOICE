<?php
// Define constant to prevent direct access
define('IN_CAMPUS_VOICE', true);

// Include necessary files
require_once __DIR__ . '/includes/auth.php';

// Call the logout function
adminLogout();

// This will redirect to login page and the function will handle the rest
exit;
?>
