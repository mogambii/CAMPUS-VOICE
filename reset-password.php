<?php
session_start();
require_once 'includes/functions.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

// Verify token
if (empty($token)) {
    header('Location: login.php');
    exit;
}

try {
    $db = getDB();
    
    // Check if token is valid and not expired (1 hour expiry)
    $stmt = $db->prepare("
        SELECT email 
        FROM password_resets 
        WHERE token = ? AND created_at > NOW() - INTERVAL 1 HOUR
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reset) {
        $error = 'Invalid or expired reset link. Please request a new password reset.';
    }
    
    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($password) || empty($confirm_password)) {
            $error = 'Please fill in all fields';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match';
        } else {
            try {
                // Begin transaction
                $db->beginTransaction();
                
                // Get user ID first
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$reset['email']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    // Update user's password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $updateStmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $updateStmt->execute([$hashed_password, $user['id']]);
                    
                    // Log the password change
                    logActivity($user['id'], 'password_reset', 'Password reset via email');
                    
                    // Delete the used token
                    $db->prepare("DELETE FROM password_resets WHERE token = ?")->execute([$token]);
                    
                    // Delete all other tokens for this email
                    $db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$reset['email']]);
                    
                    // Commit transaction
                    $db->commit();
                    
                    $success = 'Your password has been reset successfully! You can now login with your new password.';
                    $showForm = false;
                } else {
                    throw new Exception('User not found');
                }
                
            } catch (PDOException $e) {
                $db->rollBack();
                $error = 'An error occurred while resetting your password. Please try again.';
                error_log("Password reset error: " . $e->getMessage());
            }
        }
    }
    
} catch (PDOException $e) {
    $error = 'An error occurred. Please try again later.';
    error_log("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Campus Voice</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4e73df;
            --primary-hover: #2e59d9;
            --secondary-color: #6c757d;
            --light: #f8f9fc;
            --dark: #5a5c69;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f8f9fc 0%, #e3e6f0 100%);
            height: 100vh;
            display: flex;
            align-items: center;
        }
        
        .auth-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.08);
            padding: 2.5rem;
            border: 1px solid rgba(0, 0, 0, 0.05);
            max-width: 500px;
            width: 90%;
            margin: 0 auto;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border: none;
            padding: 0.65rem 1.5rem;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
        }
        
        .form-control:focus {
            border-color: #bac8f3;
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }
        
        .input-group-text {
            background-color: #f8f9fc;
            border-right: none;
            color: #6e707e;
        }
        
        .password-strength {
            height: 5px;
            background-color: #e9ecef;
            margin-top: 5px;
            border-radius: 3px;
            overflow: hidden;
        }
        
        .password-strength-fill {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease, background-color 0.3s ease;
        }
        
        .password-requirements {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 0.5rem;
        }
        
        .requirement {
            display: flex;
            align-items: center;
            margin-bottom: 0.25rem;
        }
        
        .requirement i {
            margin-right: 0.5rem;
            font-size: 0.7rem;
        }
        
        .requirement.valid {
            color: #28a745;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="auth-card">
                    <div class="text-center mb-4">
                        <i class="fas fa-key fa-3x mb-3" style="color: var(--primary-color);"></i>
                        <h2>Reset Your Password</h2>
                        <p class="text-muted">Create a new password for your account</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo $success; ?>
                            <div class="mt-3">
                                <a href="login.php" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt me-2"></i>Go to Login
                                </a>
                            </div>
                        </div>
                    <?php elseif (empty($error)): ?>
                    
                    <form method="POST" action="" id="resetForm">
                        <div class="mb-3">
                            <label for="password" class="form-label">New Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="Enter new password" required minlength="8">
                                <button class="btn btn-outline-secondary toggle-password" type="button">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="password-strength">
                                <div class="password-strength-fill"></div>
                            </div>
                            <div class="password-requirements">
                                <div class="requirement" id="length">
                                    <i class="fas fa-circle"></i>
                                    <span>At least 8 characters</span>
                                </div>
                                <div class="requirement" id="uppercase">
                                    <i class="fas fa-circle"></i>
                                    <span>At least one uppercase letter</span>
                                </div>
                                <div class="requirement" id="number">
                                    <i class="fas fa-circle"></i>
                                    <span>At least one number</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="confirm_password" 
                                       name="confirm_password" placeholder="Confirm new password" required>
                                <button class="btn btn-outline-secondary toggle-confirm-password" type="button">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback" id="password-match-feedback">
                                Passwords do not match
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 mb-3">
                            <i class="fas fa-save me-2"></i>Reset Password
                        </button>
                    </form>
                    
                    <div class="text-center mt-3">
                        <a href="login.php" class="text-decoration-none">
                            <i class="fas fa-arrow-left me-1"></i> Back to Login
                        </a>
                    </div>
                    
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        document.querySelectorAll('.toggle-password, .toggle-confirm-password').forEach(button => {
            button.addEventListener('click', function() {
                const input = this.previousElementSibling;
                const icon = this.querySelector('i');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });
        
        // Password strength meter
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const strengthFill = document.querySelector('.password-strength-fill');
        const requirements = {
            length: document.getElementById('length'),
            uppercase: document.getElementById('uppercase'),
            number: document.getElementById('number')
        };
        
        function checkPasswordStrength(password) {
            let strength = 0;
            const requirementsMet = {
                length: false,
                uppercase: false,
                number: false
            };
            
            // Check length
            if (password.length >= 8) {
                strength += 33;
                requirementsMet.length = true;
            }
            
            // Check for uppercase letters
            if (/[A-Z]/.test(password)) {
                strength += 33;
                requirementsMet.uppercase = true;
            }
            
            // Check for numbers
            if (/[0-9]/.test(password)) {
                strength += 34; // Slightly more weight to reach 100%
                requirementsMet.number = true;
            }
            
            // Update UI
            strengthFill.style.width = strength + '%';
            
            // Update strength bar color
            if (strength < 33) {
                strengthFill.style.backgroundColor = '#dc3545'; // Red
            } else if (strength < 66) {
                strengthFill.style.backgroundColor = '#fd7e14'; // Orange
            } else {
                strengthFill.style.backgroundColor = '#28a745'; // Green
            }
            
            // Update requirement indicators
            Object.keys(requirementsMet).forEach(key => {
                const requirement = requirements[key];
                const icon = requirement.querySelector('i');
                if (requirementsMet[key]) {
                    requirement.classList.add('valid');
                    icon.className = 'fas fa-check-circle';
                } else {
                    requirement.classList.remove('valid');
                    icon.className = 'fas fa-circle';
                }
            });
            
            return strength;
        }
        
        // Check password match
        function checkPasswordMatch() {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            if (password && confirmPassword) {
                if (password !== confirmPassword) {
                    confirmPasswordInput.setCustomValidity("Passwords do not match");
                    document.getElementById('password-match-feedback').style.display = 'block';
                    confirmPasswordInput.classList.add('is-invalid');
                    return false;
                } else {
                    confirmPasswordInput.setCustomValidity("");
                    document.getElementById('password-match-feedback').style.display = 'none';
                    confirmPasswordInput.classList.remove('is-invalid');
                    return true;
                }
            }
            return false;
        }
        
        // Event listeners
        passwordInput.addEventListener('input', (e) => {
            checkPasswordStrength(e.target.value);
            if (confirmPasswordInput.value) {
                checkPasswordMatch();
            }
        });
        
        confirmPasswordInput.addEventListener('input', checkPasswordMatch);
        
        // Form validation
        document.getElementById('resetForm').addEventListener('submit', function(e) {
            if (!checkPasswordMatch()) {
                e.preventDefault();
                return false;
            }
            
            const password = passwordInput.value;
            if (password.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long');
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>
