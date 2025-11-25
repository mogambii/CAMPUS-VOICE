<?php
// Social Media Integration API
header('Content-Type: application/json');
require_once '../includes/functions.php';

// This file handles fetching posts from social media platforms
// You'll need to set up API credentials in config.php

class SocialMediaIntegration {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    // Fetch Twitter posts
    public function fetchTwitterPosts() {
        if (empty(TWITTER_BEARER_TOKEN)) {
            return ['success' => false, 'message' => 'Twitter API not configured'];
        }
        
        $posts = [];
        
        foreach (CAMPUS_HASHTAGS as $hashtag) {
            $url = "https://api.twitter.com/2/tweets/search/recent?query=" . urlencode($hashtag) . "&max_results=10";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . TWITTER_BEARER_TOKEN
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200) {
                $data = json_decode($response, true);
                if (isset($data['data'])) {
                    foreach ($data['data'] as $tweet) {
                        $posts[] = [
                            'platform' => 'twitter',
                            'post_id' => $tweet['id'],
                            'content' => $tweet['text'],
                            'posted_at' => $tweet['created_at'] ?? null
                        ];
                    }
                }
            }
        }
        
        return ['success' => true, 'posts' => $posts];
    }
    
    // Fetch Instagram posts
    public function fetchInstagramPosts() {
        if (empty(INSTAGRAM_ACCESS_TOKEN)) {
            return ['success' => false, 'message' => 'Instagram API not configured'];
        }
        
        $posts = [];
        
        // Instagram Graph API implementation
        // Note: Instagram API requires business account and approved app
        
        foreach (CAMPUS_HASHTAGS as $hashtag) {
            $hashtag_clean = str_replace('#', '', $hashtag);
            $url = "https://graph.instagram.com/ig_hashtag_search?user_id=YOUR_USER_ID&q=" . urlencode($hashtag_clean) . "&access_token=" . INSTAGRAM_ACCESS_TOKEN;
            
            // Implementation would continue here with actual API calls
        }
        
        return ['success' => true, 'posts' => $posts];
    }
    
    // Save posts to database
    public function savePosts($posts) {
        $saved_count = 0;
        
        foreach ($posts as $post) {
            try {
                $stmt = $this->db->prepare("
                    INSERT INTO social_media_posts (platform, post_id, post_url, content, author_username, posted_at, hashtags)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE fetched_at = NOW()
                ");
                
                $hashtags_json = isset($post['hashtags']) ? json_encode($post['hashtags']) : null;
                
                $stmt->execute([
                    $post['platform'],
                    $post['post_id'],
                    $post['post_url'] ?? null,
                    $post['content'],
                    $post['author_username'] ?? null,
                    $post['posted_at'] ?? null,
                    $hashtags_json
                ]);
                
                $saved_count++;
            } catch (Exception $e) {
                // Log error but continue processing
                error_log("Error saving social media post: " . $e->getMessage());
            }
        }
        
        return $saved_count;
    }
    
    // Get recent social media posts from database
    public function getRecentPosts($limit = 20) {
        $stmt = $this->db->prepare("
            SELECT * FROM social_media_posts
            WHERE is_processed = FALSE
            ORDER BY posted_at DESC
            LIMIT ?
        ");
        
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
    
    // Analyze sentiment of posts (basic implementation)
    public function analyzeSentiment($text) {
        $positive_words = ['good', 'great', 'excellent', 'amazing', 'wonderful', 'fantastic', 'love', 'best', 'happy'];
        $negative_words = ['bad', 'poor', 'terrible', 'awful', 'worst', 'hate', 'horrible', 'disgusting', 'disappointed'];
        
        $text_lower = strtolower($text);
        $positive_count = 0;
        $negative_count = 0;
        
        foreach ($positive_words as $word) {
            $positive_count += substr_count($text_lower, $word);
        }
        
        foreach ($negative_words as $word) {
            $negative_count += substr_count($text_lower, $word);
        }
        
        if ($positive_count > $negative_count) {
            return 'positive';
        } elseif ($negative_count > $positive_count) {
            return 'negative';
        } else {
            return 'neutral';
        }
    }
}

// Handle API requests
$action = $_GET['action'] ?? 'get_posts';
$integration = new SocialMediaIntegration();

switch ($action) {
    case 'fetch_twitter':
        $result = $integration->fetchTwitterPosts();
        if ($result['success'] && !empty($result['posts'])) {
            $saved = $integration->savePosts($result['posts']);
            echo json_encode(['success' => true, 'fetched' => count($result['posts']), 'saved' => $saved]);
        } else {
            echo json_encode($result);
        }
        break;
        
    case 'fetch_instagram':
        $result = $integration->fetchInstagramPosts();
        if ($result['success'] && !empty($result['posts'])) {
            $saved = $integration->savePosts($result['posts']);
            echo json_encode(['success' => true, 'fetched' => count($result['posts']), 'saved' => $saved]);
        } else {
            echo json_encode($result);
        }
        break;
        
    case 'get_posts':
        $posts = $integration->getRecentPosts();
        echo json_encode(['success' => true, 'posts' => $posts]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
