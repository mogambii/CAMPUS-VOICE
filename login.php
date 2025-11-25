<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include helper functions
require_once 'includes/functions.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header("Location: admin/index.php");
    } else {
        header("Location: dashboard.php");
    }
    exit;
}

$error = '';
$email = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        try {
            $db = getDB();

            $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // Debug: Show what role was retrieved
                $db_role = $user['role'] ?? 'NULL';
                $session_role = $user['role'] ?? 'student';
                error_log("Login attempt - Email: $email, DB Role: '$db_role', Session Role will be: '$session_role'");
                
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['first_name'] = $user['first_name'] ?? 'User';
                $_SESSION['last_name'] = $user['last_name'] ?? '';
                $_SESSION['email'] = $user['email'] ?? '';
                $_SESSION['role'] = $session_role;
                
                // Debug: Check session role after setting
                error_log("After setting session - Role in session: '" . $_SESSION['role'] . "'");
                
                // Additional debug for admin specifically
                if ($email === 'admin@campusvoice.edu') {
                    error_log("ADMIN LOGIN DEBUG - Email: $email");
                    error_log("ADMIN LOGIN DEBUG - DB Role: '$db_role'");
                    error_log("ADMIN LOGIN DEBUG - Session Role: '" . $_SESSION['role'] . "'");
                    error_log("ADMIN LOGIN DEBUG - Role check result: " . ($_SESSION['role'] === 'admin' ? 'TRUE' : 'FALSE'));
                }

                // Redirect based on user role
                if ($_SESSION['role'] === 'admin') {
                    $redirect = 'admin/index.php';
                    error_log("FINAL REDIRECT - Admin detected, redirecting to: $redirect");
                } else {
                    // If coming from registration, go to welcome page, otherwise redirect to dashboard
                    if (isset($_GET['from']) && $_GET['from'] === 'register') {
                        $redirect = 'welcome.php';
                    } else {
                        $redirect = 'dashboard.php';
                    }
                    error_log("FINAL REDIRECT - Not admin, redirecting to: $redirect");
                }
                
                // Debug for admin specifically
                if ($email === 'admin@campusvoice.edu') {
                    error_log("ADMIN FINAL DEBUG - Session role: '" . $_SESSION['role'] . "'");
                    error_log("ADMIN FINAL DEBUG - Redirecting to: $redirect");
                    error_log("ADMIN FINAL DEBUG - Expected: admin/index.php");
                }

                header("Location: $redirect");
                exit;
            } else {
                $error = 'Invalid email or password';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . htmlspecialchars($e->getMessage());
        } catch (Exception $e) {
            $error = 'Error: ' . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Campus Voice</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            font-family: Georgia, 'Times New Roman', Times, serif;
            background-color: var(--light-color);
            height: 100vh;
            display: flex;
            align-items: center;
        }
        .auth-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: var(--shadow-md);
            padding: 2.5rem;
            border: 1px solid #e0e0e0;
        }
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: var(--light-color);
            border-radius: 6px;
            font-weight: 600;
        }
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
            color: var(--light-color);
        }
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(107, 91, 67, 0.25);
        }
        .input-group-text {
            background-color: var(--light-color);
            border-right: none;
            color: var(--dark-color);
        }
        .card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            box-shadow: var(--shadow-sm);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5">
                <div class="text-center mb-4">
                    <div class="mb-3">
                        <i class="fas fa-comments fa-3x" style="color: var(--primary-color);"></i>
                    </div>
                    <h2 class="mb-2">Welcome Back</h2>
                    <p class="text-muted">Sign in to continue to Campus Voice</p>
                    <?php if (isset($_SESSION['registration_success'])): ?>
                        <div class="alert alert-success">
                            <?php 
                                echo htmlspecialchars($_SESSION['registration_success']);
                                unset($_SESSION['registration_success']);
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <div class="card shadow">
                    <div class="card-body p-4">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" required 
                                           value="<?php echo htmlspecialchars($email); ?>" placeholder="Enter your email">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required
                                           placeholder="Enter your password">
                                </div>
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="remember">
                                <label class="form-check-label" for="remember">Remember me</label>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 mb-3 py-2">
                                <i class="fas fa-sign-in-alt me-2"></i>Sign In
                            </button>

                            <div class="text-center">
                                <p class="mb-0">Don't have an account? <a href="register.php">Register here</a></p>
                                <a href="forgot-password.php" class="text-muted small">Forgot Password?</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="text-center mt-3">
                    <a href="index.html" class="text-muted"><i class="fas fa-arrow-left me-2"></i>Back to Home</a>
                </div>
                </div>
            </div>
        </div>
    </div>

    <script src="main.js"></script>
</body>
</html>
