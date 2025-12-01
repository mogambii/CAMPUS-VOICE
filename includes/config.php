<?php
/**
 * Main Configuration File - Campus Voice
 * Prevents multiple includes and session starts
 */

// Prevent direct access
defined('IN_CAMPUS_VOICE') or die('Direct access not allowed');

// Check if config is already included
if (defined('CAMPUS_VOICE_CONFIG_LOADED')) {
    return;
}

define('CAMPUS_VOICE_CONFIG_LOADED', true);

// Database Configuration
define('DB_HOST', 'mysql-database-docswcw4cs0gcckk40c04cww');
define('DB_USER', 'root');
define('DB_PASS', 'lUlceU2YvoQTQ6dfmPAFPJqSJKETMSRV3ZsqBySgoAzTL4k4sRNWbRiu6hZhsjoZ');
define('DB_NAME', 'default');
define('DB_PORT', '5432');

// OpenAI API Configuration
define('OPENAI_API_KEY', 'your-api-key-here'); // Replace with your actual OpenAI API key
define('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'); // or 'text-embedding-3-large' for better results
define('DB_NAME', 'campus_voice');

// Site Configuration
define('SITE_NAME', 'Campus Voice');
define('SITE_URL', 'http://localhost/CAMPUS VOICE');
define('SITE_EMAIL', 'support@campusvoice.edu');

// File Upload Configuration
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB in bytes
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx']);

// Session Configuration
define('SESSION_LIFETIME', 3600); // 1 hour in seconds

// Pagination
define('ITEMS_PER_PAGE', 10);

// Social Media API Configuration
define('TWITTER_API_KEY', 'your_twitter_api_key');
define('TWITTER_API_SECRET', 'your_twitter_api_secret');
define('TWITTER_BEARER_TOKEN', 'your_twitter_bearer_token');

define('INSTAGRAM_ACCESS_TOKEN', 'your_instagram_access_token');
define('INSTAGRAM_CLIENT_ID', 'your_instagram_client_id');

define('TIKTOK_API_KEY', 'your_tiktok_api_key');

// Campus Hashtags to Monitor
define('CAMPUS_HASHTAGS', [
    '#Strathmore',
    '#StrathmoreUniversity',
    '#CampusVoice',
    '#StudentFeedback'
]);

// AI/NLP Configuration
define('AI_API_ENDPOINT', 'http://localhost:5000/api/check-duplicate');
define('AI_API_KEY', 'your_ai_api_key');
define('SIMILARITY_THRESHOLD', 0.75); // 75% similarity threshold

// Email Configuration (for notifications)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your_email@gmail.com');
define('SMTP_PASSWORD', 'your_email_password');
define('SMTP_FROM_EMAIL', 'noreply@campusvoice.edu');
define('SMTP_FROM_NAME', 'Campus Voice');

// Security
define('ENCRYPTION_KEY', 'your_encryption_key_here');
define('PASSWORD_MIN_LENGTH', 8);

// Timezone
date_default_timezone_set('Africa/Nairobi');

// Error Reporting (Set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session handling is now in functions.php to prevent conflicts
?>
