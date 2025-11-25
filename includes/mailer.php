<?php
/**
 * Email Notification System for Campus Voice
 */
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';

class Mailer {
    private $mail;
    private $siteName;
    private $siteEmail;
    private $siteUrl;

    public function __construct() {
        $this->mail = new PHPMailer(true);
        $this->siteName = SITE_NAME;
        $this->siteEmail = SITE_EMAIL;
        $this->siteUrl = SITE_URL;
        
        // Server settings
        $this->mail->isSMTP();
        $this->mail->Host = 'smtp.gmail.com'; // Default Gmail SMTP
        $this->mail->SMTPAuth = true;
        $this->mail->Username = 'your-email@gmail.com'; // Update this
        $this->mail->Password = 'your-app-password'; // Use App Password for Gmail
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port = 587;
        
        // Sender info
        $this->mail->setFrom($this->siteEmail, $this->siteName);
        $this->mail->isHTML(true);
    }

    /**
     * Send notification when admin responds to feedback
     */
    public function sendResponseNotification($userEmail, $userName, $feedbackTitle, $adminResponse, $feedbackLink) {
        try {
            $subject = "Update on Your Feedback: " . $feedbackTitle;
            
            // HTML email template
            $message = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>$subject</title>
                <style>
                    body { font-family: Georgia, 'Times New Roman', Times, serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { 
                        background-color: #6b5b43; 
                        color: #faf8f3; 
                        padding: 20px; 
                        text-align: center; 
                        border-radius: 5px 5px 0 0;
                    }
                    .content { 
                        padding: 20px; 
                        background-color: #fff; 
                        border: 1px solid #e0e0e0;
                        border-top: none;
                    }
                    .button {
                        display: inline-block;
                        padding: 10px 20px;
                        background-color: #6b5b43;
                        color: #fff !important;
                        text-decoration: none;
                        border-radius: 4px;
                        margin: 15px 0;
                    }
                    .footer { 
                        margin-top: 20px; 
                        font-size: 12px; 
                        color: #777; 
                        text-align: center;
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h2>$this->siteName</h2>
                    </div>
                    <div class="content">
                        <p>Dear $userName,</p>
                        <p>An administrator has responded to your feedback: <strong>$feedbackTitle</strong></p>
                        <div style="background-color: #f9f9f9; padding: 15px; border-left: 4px solid #6b5b43; margin: 15px 0;">
                            $adminResponse
                        </div>
                        <p>You can view the full conversation by clicking the button below:</p>
                        <p>
                            <a href="$feedbackLink" class="button">View Feedback</a>
                        </p>
                        <p>Thank you for your contribution to our community.</p>
                        <p>Best regards,<br>$this->siteName Team</p>
                    </div>
                    <div class="footer">
                        <p>This is an automated message. Please do not reply to this email.</p>
                        <p>&copy; " . date('Y') . " $this->siteName. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>";

            $this->mail->addAddress($userEmail, $userName);
            $this->mail->Subject = $subject;
            $this->mail->Body = $message;
            $this->mail->AltBody = strip_tags(str_replace(['<br>', '<p>', '</p>'], ["\n", "\n\n", ""], $message));

            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Mailer Error: " . $this->mail->ErrorInfo);
            return false;
        }
    }
}

// Helper function to send notification
defunction sendFeedbackResponseEmail($userId, $feedbackId, $feedbackTitle, $responseContent) {
    global $db;
    
    // Get user details
    $stmt = $db->prepare("SELECT u.email, u.full_name 
                         FROM users u 
                         INNER JOIN feedback f ON u.id = f.user_id 
                         WHERE f.id = ? AND f.user_id = ?");
    $stmt->execute([$feedbackId, $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) return false;
    
    // Create feedback link
    $feedbackLink = SITE_URL . "/view-feedback.php?id=" . $feedbackId;
    
    // Send email
    $mailer = new Mailer();
    return $mailer->sendResponseNotification(
        $user['email'],
        $user['full_name'],
        $feedbackTitle,
        $responseContent,
        $feedbackLink
    );
}
?>
