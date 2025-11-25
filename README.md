# Campus Voice - Web-Based Feedback System

A comprehensive web-based feedback system designed to facilitate effective communication between students and university administration.

## Features

- **User Authentication**: Secure login and registration system
- **Feedback Submission**: Submit feedback with categories, priorities, and anonymous options
- **AI Duplicate Detection**: Automatically detect similar feedback submissions
- **Social Media Integration**: Track campus-related posts from Twitter, Instagram, TikTok
- **Real-time Status Tracking**: Monitor feedback progress from submission to resolution
- **Admin Dashboard**: Comprehensive management interface for administrators
- **Polls & Surveys**: Engage students in campus decision-making
- **Notifications**: Keep users informed about feedback updates

## Technology Stack

- **Frontend**: HTML5, CSS3, JavaScript, Bootstrap 5
- **Backend**: PHP 7.4+
- **Database**: MySQL 8.0+
- **Server**: Apache (XAMPP)
- **APIs**: Social Media APIs (Twitter, Instagram, TikTok)

## Installation

### Prerequisites

- XAMPP (or similar LAMP/WAMP stack)
- PHP 7.4 or higher
- MySQL 8.0 or higher
- Web browser

### Setup Instructions

1. **Clone or Download the Project**
   ```
   Place the C.VOICE folder in your htdocs directory (e.g., C:\xampp\htdocs\C.VOICE)
   ```

2. **Start XAMPP**
   - Start Apache and MySQL services

3. **Create Database**
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Create a new database named `campus_voice`
   - Import the schema: `database/schema.sql`

4. **Configure Database Connection**
   - Open `includes/config.php`
   - Update database credentials if needed:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     define('DB_NAME', 'campus_voice');
     ```

5. **Configure Social Media APIs** (Optional)
   - Open `includes/config.php`
   - Add your API keys for Twitter, Instagram, TikTok

6. **Access the Application**
   - Open browser and navigate to: `http://localhost/C.VOICE`

## Default Admin Credentials

- **Email**: admin@campusvoice.edu
- **Password**: admin123

**Important**: Change the default admin password after first login!

## Project Structure

```
C.VOICE/
├── admin/              # Admin panel pages
├── api/                # API endpoints
├── assets/             # CSS, JS, images
├── database/           # Database schema
├── includes/           # PHP configuration and functions
├── uploads/            # User uploaded files
├── index.html          # Landing page
├── login.php           # Login page
├── register.php        # Registration page
├── dashboard.php       # User dashboard
└── README.md           # This file
```

## Key Features Explained

### AI Duplicate Detection

The system uses MySQL FULLTEXT search combined with Levenshtein distance algorithm to detect similar feedback submissions. This helps prevent duplicate submissions and allows users to support existing feedback.

### Social Media Integration

Monitors campus-related hashtags across social media platforms:
- Automatically fetches posts with specified hashtags
- Stores posts in database for admin review
- Can convert social media posts to feedback entries
- Sentiment analysis for post categorization

### Security Features

- Password hashing using PHP's password_hash()
- SQL injection prevention using prepared statements
- XSS protection through input sanitization
- CSRF protection
- Session management
- File upload validation

## Configuration

### Campus Hashtags

Edit `includes/config.php` to customize monitored hashtags:
```php
define('CAMPUS_HASHTAGS', [
    '#Strathmore',
    '#StrathmoreUniversity',
    '#CampusVoice',
    '#StudentFeedback'
]);
```

### Email Notifications

Configure SMTP settings in `includes/config.php` for email notifications.

## Usage

### For Students

1. Register with your student credentials
2. Login to your dashboard
3. Submit feedback by clicking "Submit Feedback"
4. Track your feedback status in real-time
5. Participate in polls and surveys
6. Receive notifications on feedback updates

### For Administrators

1. Login with admin credentials
2. Access admin panel from dashboard
3. Review and respond to feedback
4. Update feedback status
5. Monitor social media posts
6. Generate reports
7. Manage users and categories

## API Endpoints

- `api/check_duplicate.php` - AI duplicate detection
- `api/submit_feedback.php` - Submit new feedback
- `api/social_media.php` - Social media integration

## Contributing

This project was developed as part of a university initiative to improve campus engagement and communication.

## Alignment with SDGs

This project aligns with:
- **SDG 4**: Quality Education
- **SDG 16**: Peace, Justice, and Strong Institutions

## Support

For support or questions, contact: support@campusvoice.edu

## License

© 2025 Campus Voice. All rights reserved.
