<?php
// Prevent direct access to this file
if (!defined('IN_CAMPUS_VOICE')) {
    // If this file is accessed directly, set the constant and continue
    if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
        define('IN_CAMPUS_VOICE', true);
    } else {
        die('Direct access not allowed');
    }
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function to check if user is logged in as admin
function requireAdmin() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        // Store the requested URL for redirection after login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        
        // Redirect to main login page
        header('Location: ../login.php');
        exit;
    }
}

// Function to check if user is logged in as admin (without redirect)
function isAdminLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Function to log out
function adminLogout() {
    // Unset all session variables
    $_SESSION = array();
    
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
    
    // Redirect to main login page
    header('Location: ../login.php');
    exit;
}

// Redirects admin to main dashboard if already logged in
function redirectAdminIfLoggedIn() {
    if (isAdminLoggedIn()) {
        // If already in admin section, don't redirect
        // Only redirect if trying to access admin login page directly
        if (basename($_SERVER['PHP_SELF']) === 'login.php') {
            header("Location: index.php");
            exit;
        }
    }
}
