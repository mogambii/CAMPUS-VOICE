<?php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
function getDB() {
    static $pdo;
    if (!isset($pdo)) {
        $config = require __DIR__ . '/../config/database.php';
        $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4";
        
        try {
            $pdo = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }
    return $pdo;
}

// Sanitize input
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Redirect if user is logged in and not on the registration page
function redirectIfLoggedIn() {
    $current_page = basename($_SERVER['PHP_SELF']);
    if (isLoggedIn() && $current_page !== 'register.php') {
        $role = $_SESSION['role'] ?? 'user';
        if ($role === 'admin') {
            header('Location: admin/index.php');
        } else {
            header('Location: dashboard.php');
        }
        exit();
    }
}

// Redirect to login if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Verify password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Hash password
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Log activity (temporarily disabled)
function logActivity($userId, $action) {
    // Temporarily disabled until activity_log table is created
    // To enable logging, create the activity_log table and uncomment the code below
    /*
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO activity_log (user_id, action, ip_address, user_agent) VALUES (?, ?, ?, ?)");
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    try {
        $stmt->execute([$userId, $action, $ip, $userAgent]);
        return true;
    } catch (PDOException $e) {
        error_log("Error logging activity: " . $e->getMessage());
        return false;
    }
    */
    return true; // Return true to prevent login/logout issues
}

/**
 * Check if the current user is an admin
 * @return bool True if user is admin, false otherwise
 */
function isAdmin() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Get the current logged-in user's information
 * @return array|bool User data if found, false if not logged in or user not found
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return false;
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if ($user) {
        // Remove sensitive data
        unset($user['password']);
        unset($user['reset_token']);
        unset($user['reset_token_expires']);
        
        // Add full name
        $user['full_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
        
        // Add avatar URL if not set
        if (empty($user['avatar'])) {
            $user['avatar'] = 'assets/img/avatar.png';
        }
        
        return $user;
    }
    
    return false;
}

/**
 * Get Bootstrap badge class for status
 * @param string $status The status to get badge for
 * @return string Bootstrap badge class
 */
function getStatusBadge($status) {
    $status = strtolower(trim($status));
    $badge = '';
    
    switch ($status) {
        case 'pending':
            $badge = 'bg-warning';
            break;
        case 'in_progress':
            $badge = 'bg-primary';
            break;
        case 'resolved':
            $badge = 'bg-success';
            break;
        case 'rejected':
            $badge = 'bg-danger';
            break;
        default:
            $badge = 'bg-secondary';
            break;
    }
    
    // Return the badge with consistent styling
    return $badge . ' text-white';
}

/**
 * Format a date string into a more readable format
 * @param string $date The date string to format
 * @param string $format The format to use (default: 'M d, Y h:i A')
 * @return string Formatted date string
 */
function formatDate($date, $format = 'M d, Y h:i A') {
    if (empty($date) || $date === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    try {
        $dateTime = new DateTime($date);
        return $dateTime->format($format);
    } catch (Exception $e) {
        return 'Invalid date';
    }
}

/**
 * Convert a timestamp to a human-readable time ago format
 * @param string $datetime MySQL datetime string
 * @return string Human-readable time difference (e.g., "2 hours ago")
 */
function timeAgo($datetime) {
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return 'Just now';
    }
    
    $time = strtotime($datetime);
    $timeDiff = time() - $time;
    
    // Less than a minute
    if ($timeDiff < 60) {
        return 'Just now';
    }
    
    $intervals = [
        31536000 => 'year',
        2592000 => 'month',
        604800 => 'week',
        86400 => 'day',
        3600 => 'hour',
        60 => 'minute',
        1 => 'second'
    ];
    
    foreach ($intervals as $seconds => $label) {
        $diff = floor($timeDiff / $seconds);
        if ($diff >= 1) {
            if ($diff === 1) {
                return $diff . ' ' . $label . ' ago';
            } else {
                return $diff . ' ' . $label . 's ago';
            }
        }
    }
    
    return 'Just now';
}
