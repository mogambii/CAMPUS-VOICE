<?php
// =============================
// FUNCTIONS - Campus Voice
// =============================

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

// Redirect if user is logged in
function redirectIfLoggedIn() {
    if (isLoggedIn()) {
        header('Location: dashboard.php');
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

// Log activity
function logActivity($userId, $action) {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO activity_log (user_id, action, ip_address, user_agent) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $userId,
            $action,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("Failed to log activity: " . $e->getMessage());
        return false;
    }
}
