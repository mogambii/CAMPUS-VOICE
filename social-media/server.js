require('dotenv').config();
const express = require('express');
const axios = require('axios');
const cheerio = require('cheerio');
const mysql = require('mysql2/promise');
const cron = require('node-cron');

const app = express();
const PORT = process.env.PORT || 3001;

// Database connection
const dbConfig = {
    host: process.env.DB_HOST || 'localhost', 
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_NAME || 'campus_voice',
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0
};

const pool = mysql.createPool(dbConfig);

// Create social_mentions table if it doesn't exist
async function initializeDatabase() {
    const connection = await pool.getConnection();
    try {
        await connection.query(`
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
        `);
        console.log('Database initialized');
    } catch (error) {
        console.error('Error initializing database:', error);
    } finally {
        connection.release();
    }
}

// Fetch Reddit posts
async function fetchRedditPosts() {
    try {
        const response = await axios.get('https://www.reddit.com/search.json?q=Strathmore%20University&limit=20', {
            headers: {
                'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            }
        });

        const posts = response.data.data.children;
        const connection = await pool.getConnection();

        for (const post of posts) {
            const { data } = post;
            if (!data) continue;

            try {
                await connection.query(
                    'INSERT IGNORE INTO social_mentions (platform, username, content, post_url, created_at, post_date) VALUES (?, ?, ?, ?, NOW(), ?)',
                    [
                        'reddit',
                        data.author,
                        `${data.title}\n\n${data.selftext || ''}`.substring(0, 2000),
                        `https://reddit.com${data.permalink}`,
                        new Date(data.created_utc * 1000)
                    ]
                );
            } catch (error) {
                if (error.code !== 'ER_DUP_ENTRY') {
                    console.error('Error saving Reddit post:', error);
                }
            }
        }
        connection.release();
        console.log('Reddit posts fetched and saved');
    } catch (error) {
        console.error('Error fetching Reddit posts:', error.message);
    }
}

// Fetch Twitter posts using Nitter
async function fetchTwitterPosts() {
    try {
        const response = await axios.get('https://nitter.net/search?f=tweet&q=Strathmore%20University', {
            headers: {
                'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            }
        });

        const $ = cheerio.load(response.data);
        const tweets = [];
        const connection = await pool.getConnection();

        $('.timeline-item').each((i, el) => {
            const username = $(el).find('.username').first().text().trim();
            const content = $(el).find('.tweet-content').text().trim();
            const timestamp = $(el).find('.tweet-date a').attr('title');
            const postUrl = `https://nitter.net${$(el).find('.tweet-link').attr('href')}`;

            if (username && content) {
                tweets.push({
                    username,
                    content,
                    postUrl,
                    timestamp: new Date(timestamp)
                });
            }
        });

        for (const tweet of tweets) {
            try {
                await connection.query(
                    'INSERT IGNORE INTO social_mentions (platform, username, content, post_url, created_at, post_date) VALUES (?, ?, ?, ?, NOW(), ?)',
                    [
                        'twitter',
                        tweet.username,
                        tweet.content.substring(0, 2000),
                        tweet.postUrl,
                        tweet.timestamp
                    ]
                );
            } catch (error) {
                if (error.code !== 'ER_DUP_ENTRY') {
                    console.error('Error saving tweet:', error);
                }
            }
        }
        connection.release();
        console.log('Twitter posts fetched and saved');
    } catch (error) {
        console.error('Error fetching Twitter posts:', error.message);
    }
}

// Schedule tasks to run every 10 minutes
cron.schedule('*/10 * * * *', async () => {
    console.log('Running social media scraper...');
    await fetchRedditPosts();
    await fetchTwitterPosts();
    console.log('Social media scrape completed');
});

// API Endpoints
app.get('/api/mentions', async (req, res) => {
    try {
        const [rows] = await pool.query(
            'SELECT * FROM social_mentions ORDER BY post_date DESC LIMIT 100'
        );
        res.json(rows);
    } catch (error) {
        console.error('Error fetching mentions:', error);
        res.status(500).json({ error: 'Failed to fetch mentions' });
    }
});

// Initialize database and start server
async function startServer() {
    await initializeDatabase();
    
    // Initial fetch
    await fetchRedditPosts();
    await fetchTwitterPosts();
    
    app.listen(PORT, () => {
        console.log(`Server running on http://localhost:${PORT}`);
    });
}

startServer();
