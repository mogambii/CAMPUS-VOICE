<?php
session_start();
require_once 'includes/functions.php';

$message = '';
$error = '';
$success = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    
    if (empty($email)) {
        $error = 'Please enter your email address';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        try {
            $db = getDB();
            
            // Check if email exists
            $stmt = $db->prepare("SELECT id, first_name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Generate token
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Delete any existing tokens for this email
                $db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
                
                // Insert new token
                $stmt = $db->prepare("INSERT INTO password_resets (email, token, created_at) VALUES (?, ?, ?)");
                $stmt->execute([$email, $token, $expires]);
                
                // Generate reset link
                $resetLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . "/CAMPUS%20VOICE/reset-password.php?token=" . $token;
                
                // Email content
                $to = $email;
                $subject = "Password Reset Request - Campus Voice";
                
                $emailMessage = "
                <html>
                <head>
                    <title>Password Reset</title>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .button {
                            display: inline-block; 
                            padding: 10px 20px; 
                            background-color: #4e73df; 
                            color: white; 
                            text-decoration: none; 
                            border-radius: 4px;
                            margin: 15px 0;
                        }
                        .footer { margin-top: 30px; font-size: 12px; color: #777; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <h2>Password Reset Request</h2>
                        <p>Hello " . htmlspecialchars($user['first_name']) . ",</p>
                        <p>You recently requested to reset your password for your Campus Voice account. Click the button below to reset it:</p>
                        <p><a href='$resetLink' class='button'>Reset Password</a></p>
                        <p>Or copy and paste this link into your browser:</p>
                        <p><small>$resetLink</small></p>
                        <p>This password reset link will expire in 1 hour.</p>
                        <p>If you did not request a password reset, please ignore this email or contact support if you have any concerns.</p>
                        <div class='footer'>
                            <p>This is an automated message, please do not reply to this email.</p>
                        </div>
                    </div>
                </body>
                </html>";
                
                // Email headers
                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $headers .= 'From: Campus Voice <no-reply@campusvoice.edu>' . "\r\n";
                $headers .= 'X-Mailer: PHP/' . phpversion();
                
                // For local testing, we'll show the link on the page
                if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || 
                    strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
                    $message = "<div class='alert alert-info'>
                        <h5>Local Development Notice</h5>
                        <p>In a production environment, an email would be sent to $email with a password reset link.</p>
                        <p>For testing, here's the reset link: <a href='$resetLink' target='_blank'>$resetLink</a></p>
                        <p><small>Email content preview:</small></p>
                        <div style='border:1px solid #ddd; padding:10px; margin:10px 0; background:#f9f9f9;'>" . 
                        nl2br(htmlspecialchars(strip_tags($emailMessage))) . 
                        "</div>
                    </div>";
                } else {
                    // In production, send the actual email
                    if (mail($to, $subject, $emailMessage, $headers)) {
                        $success = 'A password reset link has been sent to your email address. Please check your inbox and follow the instructions to reset your password.';
                    } else {
                        $error = 'Failed to send password reset email. Please try again later.';
                        error_log('Failed to send password reset email to: ' . $email);
                    }
                }
                
                $success = "If your email exists in our system, you will receive a password reset link shortly.";
            } else {
                // For security, don't reveal if the email exists or not
                $success = "If your email exists in our system, you will receive a password reset link shortly.";
            }
        } catch (PDOException $e) {
            $error = "An error occurred. Please try again later.";
            error_log("Password reset error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Campus Voice</title>
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
        
        .card {
            border: none;
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.08);
            border-radius: 15px;
            overflow: hidden;
        }
        
        .reset-instructions {
            background-color: #f8f9fc;
            border-left: 4px solid var(--primary-color);
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 4px;
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
                        <h2>Forgot Password</h2>
                        <p class="text-muted">Enter your email address to reset your password</p>
                    </div>

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo $success; ?>
                            <?php if (isset($message) && !empty($message)): ?>
                                <div class="mt-2 p-2 bg-light rounded">
                                    <small class="text-muted"><?php echo $message; ?></small>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (empty($success)): ?>
                    <div class="reset-instructions">
                        <p class="mb-0 small">Enter the email address associated with your account and we'll send you a link to reset your password.</p>
                    </div>

                    <form method="POST" action="">
                        <div class="mb-4">
                            <label for="email" class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($email ?? ''); ?>" 
                                       placeholder="Enter your email" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 mb-3">
                            <i class="fas fa-paper-plane me-2"></i>Send Reset Link
                        </button>
                    </form>
                    <?php endif; ?>

                    <div class="text-center mt-3">
                        <a href="login.php" class="text-decoration-none">
                            <i class="fas fa-arrow-left me-1"></i> Back to Login
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
