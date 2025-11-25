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

// Check if user is logged in as admin
requireAdmin();

$error = '';
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Current admin credentials (in a real app, this would come from a database)
    $admin_username = 'admin';
    $admin_password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'; // 'admin123'
    
    // Validate current password
    if (!password_verify($current_password, $admin_password_hash)) {
        $error = 'Current password is incorrect';
    } 
    // Validate new password
    elseif (strlen($new_password) < 8) {
        $error = 'New password must be at least 8 characters long';
    }
    // Check if passwords match
    elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match';
    }
    // Update password
    else {
        // Generate new hash
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update the login.php file
        $login_file = __DIR__ . '/login.php';
        $login_content = file_get_contents($login_file);
        
        // Replace the old hash with the new one
        $new_login_content = preg_replace(
            '/(\$admin_password_hash\s*=\s*\')([^\']*)(\';\s*\/\/.*)/', 
            '\\1' . $new_hash . '\\3', 
            $login_content
        );
        
        if (file_put_contents($login_file, $new_login_content) !== false) {
            $success = 'Password updated successfully!';
        } else {
            $error = 'Failed to update password. Please check file permissions.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-dashboard">
    <div class="d-flex">
        <!-- Sidebar -->
        <?php include 'includes/admin_sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex-grow-1">
            <!-- Top Navigation -->
            <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
                <div class="container-fluid">
                    <button class="btn btn-link text-white me-3 d-lg-none" type="button" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <span class="navbar-brand">Change Password</span>
                    <div class="d-flex align-items-center ms-auto">
                        <a href="index.php" class="btn btn-outline-light btn-sm me-2">
                            <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </nav>

            <!-- Main Content -->
            <div class="container-fluid p-4">
                <div class="row justify-content-center">
                    <div class="col-md-8 col-lg-6">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Change Password</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($error): ?>
                                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                                <?php endif; ?>
                                
                                <?php if ($success): ?>
                                    <div class="alert alert-success">
                                        <?php echo htmlspecialchars($success); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <form method="POST" action="">
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-key"></i></span>
                                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                                        </div>
                                        <div class="form-text">Password must be at least 8 characters long</div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-check-circle"></i></span>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Update Password
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        document.querySelectorAll('.password-toggle').forEach(button => {
            button.addEventListener('click', function() {
                const input = this.previousElementSibling;
                const type = input.type === 'password' ? 'text' : 'password';
                input.type = type;
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });
        });
    </script>
</body>
</html>
