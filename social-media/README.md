# Social Media Monitor for Strathmore University

This service monitors social media platforms for mentions of "Strathmore University" and provides an API to access the collected data.

## Features

- Fetches posts from Reddit mentioning "Strathmore University"
- Fetches tweets mentioning "Strathmore University" using Nitter
- Stores all mentions in a MySQL database
- Provides a REST API to access the collected data
- Updates automatically every 10 minutes

## Prerequisites

- Node.js (v14 or higher)
- MySQL database
- npm or yarn

## Setup

1. Clone the repository
2. Navigate to the project directory
3. Install dependencies:
   ```bash
   npm install
   ```
4. Copy `.env.example` to `.env` and update the database credentials
5. Start the server:
   ```bash
   npm start
   ```

## API Endpoints

- `GET /api/mentions` - Get all collected mentions

## Database Schema

The service uses a single table called `social_mentions` with the following structure:

```sql
CREATE TABLE IF NOT EXISTS social_mentions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    platform VARCHAR(20) NOT NULL,
    username VARCHAR(100) NOT NULL,
    content TEXT NOT NULL,
    post_url VARCHAR(500) NOT NULL,
    created_at DATETIME NOT NULL,
    post_date DATETIME NOT NULL,
    UNIQUE KEY unique_mention (platform, post_url(200))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Running in Production

For production use, consider:

1. Setting up PM2 or similar process manager
2. Configuring proper logging
3. Setting up proper error tracking
4. Implementing rate limiting on the API
