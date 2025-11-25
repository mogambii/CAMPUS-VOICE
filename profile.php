<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/includes/functions.php';
$db = getDB();

// Get current user data with explicit field selection
$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT id, first_name, last_name, email, phone, department, year_of_study, role, created_at FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Debug: Log the user data for verification
error_log('User data: ' . print_r($user, true));

if (!$user) {
    // User not found, log them out
    session_destroy();
    header('Location: login.php');
    exit;
}

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Basic validation
    if (empty($first_name) || empty($email)) {
        $error_message = 'First name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } elseif (!empty($new_password) && $new_password !== $confirm_password) {
        $error_message = 'New password and confirm password do not match.';
    } else {
        try {
            $db->beginTransaction();
            
            // Update basic info
            $update_data = [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'id' => $user_id
            ];
            
            // If changing password
            $password_update = '';
            if (!empty($new_password)) {
                // Verify current password
                if (password_verify($current_password, $user['password'])) {
                    $password_update = ', password = :password';
                    $update_data['password'] = password_hash($new_password, PASSWORD_DEFAULT);
                } else {
                    throw new Exception('Current password is incorrect.');
                }
            }
            
            $sql = "UPDATE users SET 
                    first_name = :first_name,
                    last_name = :last_name,
                    email = :email
                    $password_update
                    WHERE id = :id";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($update_data);
            
            // Update session data
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name'] = $last_name;
            $_SESSION['email'] = $email;
            
            $db->commit();
            $success_message = 'Profile updated successfully!';
            
            // Refresh user data
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = 'Error updating profile: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Campus Voice</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .profile-header {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0.5rem;
        }
        .profile-pic {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid white;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        }
        .card {
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            font-weight: 600;
        }
        .form-control:focus {
            border-color: #bac8f3;
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <!-- Back Button -->
        <div class="mb-4">
            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>
        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-lg-4">
                <!-- Profile Card -->
                <div class="card mb-4">
                    <div class="card-body text-center">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['first_name'] . ' ' . $user['last_name']); ?>" 
                             alt="Profile" class="profile-pic mb-3">
                        <h4><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h4>
                        <p class="text-muted mb-1"><?php echo htmlspecialchars($user['email']); ?></p>
                        <?php 
                        if (!empty($user['created_at']) && $user['created_at'] !== '0000-00-00 00:00:00'): 
                            $date = new DateTime($user['created_at']);
                        ?>
                            <p class="text-muted">Member since <?php echo $date->format('M Y'); ?></p>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-center gap-2 mt-3">
                            <a href="dashboard.php" class="btn btn-sm btn-outline-primary px-3 py-1">
                                <i class="fas fa-home me-1"></i> Dashboard
                            </a>
                            <a href="view-feedback.php?user=me" class="btn btn-sm btn-primary px-3 py-1">
                                <i class="fas fa-comments me-1"></i> Feedback
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Account Info -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Account Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <p class="mb-1 text-muted small">Account Status</p>
                            <p class="mb-0">
                                <span class="badge bg-success">Active</span>
                            </p>
                        </div>
                        <div>
                            <p class="mb-1 text-muted small">Member Since</p>
                            <p class="mb-0">
                                <?php 
                                if (!empty($user['created_at']) && $user['created_at'] !== '0000-00-00 00:00:00') {
                                    $date = new DateTime($user['created_at']);
                                    echo $date->format('F j, Y');
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-8">
                <!-- Edit Profile Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">Edit Profile</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <label for="first_name" class="form-label">First Name</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="last_name" class="form-label">Last Name</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?php echo htmlspecialchars($user['last_name']); ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            
                            <hr class="my-4">
                            <h6 class="mb-3">Change Password</h6>
                            <p class="text-muted small mb-3">Leave blank to keep current password</p>
                            
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password">
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Account Actions -->
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h6 class="mb-0">Danger Zone</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Delete Account</h6>
                                <p class="small text-muted mb-0">Once you delete your account, there is no going back.</p>
                            </div>
                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                                <i class="fas fa-trash-alt me-1"></i> Delete Account
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete Account Modal -->
    <div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete your account? This action cannot be undone.</p>
                    <p class="text-danger">All your data will be permanently removed from our servers.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form action="delete-account.php" method="POST" class="d-inline">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash-alt me-1"></i> Delete My Account
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show password fields if user starts typing in current password
        document.getElementById('current_password').addEventListener('input', function() {
            const newPassField = document.getElementById('new_password');
            const confirmPassField = document.getElementById('confirm_password');
            
            if (this.value.length > 0) {
                newPassField.required = true;
                confirmPassField.required = true;
            } else {
                newPassField.required = false;
                confirmPassField.required = false;
            }
        });
        
        // Form validation
        const form = document.querySelector('form');
        form.addEventListener('submit', function(e) {
            const newPass = document.getElementById('new_password').value;
            const confirmPass = document.getElementById('confirm_password').value;
            
            if (newPass !== confirmPass) {
                e.preventDefault();
                alert('New password and confirm password do not match.');
            }
        });
    </script>
</body>
</html>
