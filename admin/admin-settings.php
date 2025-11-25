<?php
require_once 'includes/auth.php';
require_once '../includes/functions.php';

$error = '';
$success = '';

// Get current admin info
$admin_id = $_SESSION['admin_id'];
$db = getDB();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_username = trim($_POST['current_username']);
    $new_username = trim($_POST['new_username']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    try {
        // Verify current credentials
        $stmt = $db->prepare("SELECT * FROM admins WHERE id = ?");
        $stmt->execute([$admin_id]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$admin) {
            $error = 'Admin not found.';
        } elseif (!password_verify($current_password, $admin['password'])) {
            $error = 'Current password is incorrect.';
        } else {
            // Prepare update query
            $update_fields = [];
            $params = [];
            
            // Update username if changed
            if (!empty($new_username) && $new_username !== $current_username) {
                $update_fields[] = "username = ?";
                $params[] = $new_username;
                $_SESSION['admin_username'] = $new_username;
            }
            
            // Update password if provided
            if (!empty($new_password)) {
                if ($new_password !== $confirm_password) {
                    $error = 'New password and confirm password do not match.';
                } else {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_fields[] = "password = ?";
                    $params[] = $hashed_password;
                }
            }
            
            // If there are updates to make
            if (empty($error) && !empty($update_fields)) {
                $params[] = $admin_id;
                $update_query = "UPDATE admins SET " . implode(', ', $update_fields) . " WHERE id = ?";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->execute($params);
                
                $success = 'Admin settings updated successfully!';
            } elseif (empty($error)) {
                $success = 'No changes were made.';
            }
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

// Get current admin data
$stmt = $db->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings - Campus Voice</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .settings-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        }
        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
    </style>
</head>
<body class="admin-dashboard">
    <div class="d-flex">
        <?php include 'includes/admin_sidebar.php'; ?>
        
        <div class="flex-grow-1">
            <!-- Top Navigation -->
            <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
                <div class="container-fluid">
                    <button class="btn btn-link text-white me-3 d-lg-none" type="button" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <span class="navbar-brand">Admin Settings</span>
                </div>
            </nav>
            
            <!-- Main Content -->
            <div class="container-fluid p-4">
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card settings-card mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-user-cog me-2"></i>Update Admin Credentials</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="mb-3">
                                        <label for="current_username" class="form-label">Current Username</label>
                                        <input type="text" class="form-control" id="current_username" name="current_username" 
                                               value="<?php echo htmlspecialchars($admin['username']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="new_username" class="form-label">New Username</label>
                                        <input type="text" class="form-control" id="new_username" name="new_username" 
                                               placeholder="Leave blank to keep current username">
                                    </div>
                                    
                                    <hr class="my-4">
                                    
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <input type="password" class="form-control" id="current_password" 
                                               name="current_password" required>
                                        <div class="form-text">Enter your current password to make any changes.</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" 
                                               placeholder="Leave blank to keep current password">
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirm_password" 
                                               name="confirm_password">
                                    </div>
                                    
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button type="submit" class="btn btn-primary px-4">
                                            <i class="fas fa-save me-2"></i>Save Changes
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <div class="card settings-card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Security Tips</h5>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled">
                                    <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Use a strong, unique password</li>
                                    <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Don't share your credentials</li>
                                    <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Change your password regularly</li>
                                    <li><i class="fas fa-check-circle text-success me-2"></i> Log out when using public computers</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar on mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
        
        // Password visibility toggle
        const togglePassword = document.querySelectorAll('.toggle-password');
        togglePassword.forEach(button => {
            button.addEventListener('click', function() {
                const input = this.previousElementSibling;
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });
        });
    </script>
</body>
</html>
