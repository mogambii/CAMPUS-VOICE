<?php
// Define constant to prevent direct access
define('IN_CAMPUS_VOICE', true);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Redirect to dashboard if already logged in
redirectAdminIfLoggedIn();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Simple validation
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        // Fetch admin credentials from database
        try {
            $db = getDB();
            
            // Debug: Check if we got a database connection
            if (!$db) {
                throw new Exception("Failed to connect to database");
            }
            
            $stmt = $db->prepare("SELECT * FROM admins WHERE username = ? LIMIT 1");
            if (!$stmt->execute([$username])) {
                throw new Exception("Query failed: " . implode(" ", $stmt->errorInfo()));
            }
            
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$admin) {
                $error = 'Invalid username or password';
                error_log("Login failed: User '$username' not found");
            } else {
                $admin_username = $admin['username'];
                $admin_password_hash = $admin['password'];
                
                // Debug logging (remove in production)
                error_log("Attempting login for user: " . $admin_username);
                error_log("Stored hash: " . $admin_password_hash);
                
                // Verify password
                if (password_verify($password, $admin_password_hash)) {
                    // Set session variables
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_username'] = $admin_username;
                    $_SESSION['admin_id'] = $admin['id'] ?? 0;
                    
                    // Regenerate session ID for security
                    session_regenerate_id(true);
                    
                    // Redirect to dashboard
                    header('Location: index.php');
                    exit;
                } else {
                    $error = 'Invalid username or password';
                    error_log("Login failed: Invalid password for user '$username'");
                }
            }
        } catch (Exception $e) {
            $error = 'An error occurred. Please try again later.';
            error_log('Admin login error: ' . $e->getMessage());
            // For debugging - remove in production
            $error .= ' Debug: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Campus Voice</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fc;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            max-width: 400px;
            width: 100%;
            padding: 2rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        }
        .login-logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-logo i {
            font-size: 3rem;
            color: #4e73df;
            margin-bottom: 1rem;
        }
        .form-control:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
        }
        .btn-login {
            background-color: #4e73df;
            border: none;
            padding: 0.6rem;
            font-weight: 600;
        }
        .btn-login:hover {
            background-color: #2e59d9;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-logo">
            <i class="fas fa-comment-dots"></i>
            <h2>Campus Voice</h2>
            <p class="text-muted">Admin Panel</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                    <input type="text" class="form-control" id="username" name="username" required autofocus>
                </div>
            </div>
            
            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary btn-login w-100 mb-3">
                <i class="fas fa-sign-in-alt me-2"></i>Login
            </button>
            
            <div class="text-center">
                <a href="../index.php" class="text-decoration-none">
                    <i class="fas fa-arrow-left me-1"></i> Back to Site
                </a>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
