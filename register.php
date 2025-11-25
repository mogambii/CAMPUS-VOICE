<?php
require_once 'includes/functions.php';

// If user is already logged in, log them out first
if (isLoggedIn()) {
    session_destroy();
    session_start(); // Start a new session for the registration process
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $first_name = sanitize($_POST['first_name'] ?? '');
    $last_name = sanitize($_POST['last_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $department = sanitize($_POST['department'] ?? '');
    $year_of_study = !empty($_POST['year_of_study']) ? (int)$_POST['year_of_study'] : null;
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        $db = getDB();
        
        // Check if email already exists
        $check_stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $check_stmt->execute([$email]);
        
        if ($check_stmt->fetch()) {
            $error = 'Email already registered';
        } else {
            // Insert new user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_stmt = $db->prepare("
                INSERT INTO users (first_name, last_name, email, phone, department, year_of_study, password)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            if ($insert_stmt->execute([$first_name, $last_name, $email, $phone, $department, $year_of_study, $hashed_password])) {
                $user_id = $db->lastInsertId();
                logActivity($user_id, 'register');
                
                // Set success message
                $success = 'Registration successful! You can now log in with your credentials.';
                
                // Start a new session for the success message
                session_regenerate_id(true);
                $_SESSION['registration_success'] = $success;
                
                // Redirect to login page
                header('Location: login.php');
                exit();
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Campus Voice</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="auth-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-11">
                    <div class="auth-card">
                        <div class="row g-0">
                            <div class="col-lg-5 auth-left">
                                <div class="text-center">
                                    <i class="fas fa-user-plus fa-5x mb-4"></i>
                                    <h2 class="mb-4">Join Campus Voice</h2>
                                    <p class="lead">Create your account and start making a difference on campus today!</p>
                                </div>
                            </div>
                            <div class="col-lg-7 auth-right">
                                <div class="text-center mb-4">
                                    <h3>Create Your Account</h3>
                                    <p class="text-muted">Fill in your details to get started</p>
                                </div>
                                
                                <?php if ($error): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <form method="POST" action="">
                                    <div class="alert alert-info py-2 small mb-3">
                                        <i class="fas fa-info-circle me-1"></i> Fields marked with <span class="text-danger">*</span> are required
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="first_name" class="form-label">First Name *</label>
                                            <input type="text" class="form-control" id="first_name" name="first_name" required 
                                                   placeholder="Enter first name" value="<?php echo htmlspecialchars($first_name ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="last_name" class="form-label">Last Name *</label>
                                            <input type="text" class="form-control" id="last_name" name="last_name" required 
                                                   placeholder="Enter last name" value="<?php echo htmlspecialchars($last_name ?? ''); ?>">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-12 mb-3">
                                            <label for="email" class="form-label">Email Address *</label>
                                            <input type="email" class="form-control" id="email" name="email" required 
                                                   placeholder="Enter Email" value="<?php echo htmlspecialchars($email ?? ''); ?>">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="phone" class="form-label">Phone Number</label>
                                            <input type="tel" class="form-control" id="phone" name="phone" 
                                                   placeholder="Enter phone number" value="<?php echo htmlspecialchars($phone ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="department" class="form-label">Department</label>
                                            <select class="form-select" id="department" name="department">
                                                <option value="">Select Department</option>
                                                <option value="Computer Science">Computer Science</option>
                                                <option value="Engineering">Engineering</option>
                                                <option value="Business">Business</option>
                                                <option value="Arts">Arts</option>
                                                <option value="Science">Science</option>
                                                <option value="Medicine">Medicine</option>
                                                <option value="Law">Law</option>
                                                <option value="Education">Education</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="year_of_study" class="form-label">Year of Study</label>
                                            <select class="form-select" id="year_of_study" name="year_of_study">
                                                <option value="1">1st Year</option>
                                                <option value="2">2nd Year</option>
                                                <option value="3">3rd Year</option>
                                                <option value="4">4th Year</option>
                                                <option value="5">5th Year</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="password" class="form-label">Password *</label>
                                            <input type="password" class="form-control" id="password" name="password" required 
                                                   placeholder="Min. 8 characters">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="confirm_password" class="form-label">Confirm Password *</label>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required 
                                                   placeholder="Re-enter password">
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary w-100 mt-3 mb-3">
                                        <i class="fas fa-user-plus me-2"></i>Create Account
                                    </button>
                                    
                                    <div class="text-center">
                                        <p class="mb-0">Already have an account? <a href="login.php">Login here</a></p>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="text-center mt-3">
                        <a href="index.html" class="text-muted"><i class="fas fa-arrow-left me-2"></i>Back to Home</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
