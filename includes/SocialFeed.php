<?php
class SocialFeed {
    private $cacheDir;
    private $cacheTime;
    
    public function __construct() {
        $this->cacheDir = __DIR__ . '/../cache/';
        $this->cacheTime = 600; // 10 minutes cache
        
        if (!file_exists($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    public function getFeed($query = 'Strathmore University') {
        $cacheFile = $this->cacheDir . 'social_feed_' . md5($query) . '.json';
        
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $this->cacheTime)) {
            return json_decode(file_get_contents($cacheFile), true);
        }
        
        $redditPosts = $this->getRedditPosts($query);
        $twitterPosts = $this->getTwitterPosts($query);
        
        $allPosts = array_merge($redditPosts, $twitterPosts);
        usort($allPosts, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        file_put_contents($cacheFile, json_encode($allPosts));
        return $allPosts;
    }
    
    private function getRedditPosts($query) {
        $url = 'https://www.reddit.com/search.json?q=' . urlencode($query) . '&limit=10';
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['User-Agent: CampusVoiceApp/1.0'],
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        if (curl_errno($ch)) return [];
        $data = json_decode($response, true);
        curl_close($ch);
        
        if (!isset($data['data']['children'])) return [];
        
        $posts = [];
        foreach ($data['data']['children'] as $post) {
            $postData = $post['data'];
            $posts[] = [
                'platform' => 'reddit',
                'id' => 'reddit_' . $postData['id'],
                'text' => $postData['title'] . "\n\n" . ($postData['selftext'] ?? ''),
                'created_at' => date('Y-m-d H:i:s', $postData['created_utc']),
                'author' => [
                    'name' => $postData['author'],
                    'username' => $postData['author'],
                    'profile_image_url' => 'https://www.redditstatic.com/avatars/defaults/v2/avatar_default_1.png'
                ],
                'url' => 'https://reddit.com' . $postData['permalink'],
                'media' => [],
                'metrics' => [
                    'likes' => $postData['ups'] - $postData['downs'],
                    'comments' => $postData['num_comments'],
                    'shares' => 0
                ]
            ];
        }
        
        return $posts;
    }
    
    private function getTwitterPosts($query) {
        $url = 'https://nitter.net/search?f=tweet&q=' . urlencode($query);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['User-Agent: Mozilla/5.0'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error || $httpCode !== 200 || empty($response)) {
            error_log("Failed to fetch tweets. HTTP Code: {$httpCode}, Error: " . ($error ?: 'Empty response'));
            return [];
        }
        
        $posts = [];
        
        // Parse HTML response using DOMDocument with error handling
        $dom = new DOMDocument();
        libxml_use_internal_errors(true); // Disable libxml errors
        
        // Check if response is valid HTML before loading
        if (empty(trim($response))) {
            error_log("Empty response from Nitter");
            return [];
        }
        
        $dom->loadHTML($response, LIBXML_NOERROR);
        libxml_clear_errors(); // Clear any errors that occurred during loading
        
        $xpath = new DOMXPath($dom);
        
        // Find all tweet elements
        $tweetElements = $xpath->query("//div[contains(@class, 'timeline-item')]");
        
        if ($tweetElements === false || $tweetElements->length === 0) {
            error_log("No tweet elements found in the response");
            return [];
        }
        
        foreach ($tweetElements as $tweetElement) {
            try {
                $username = '';
                $text = '';
                $tweetUrl = '';
                $timestamp = date('Y-m-d H:i:s'); // Default to current time
                
                // Extract username
                $usernameNodes = $xpath->query(".//a[contains(@class, 'username')]", $tweetElement);
                if ($usernameNodes->length > 0) {
                    $username = trim($usernameNodes[0]->textContent);
                }
                
                // Extract tweet text
                $textNodes = $xpath->query(".//div[contains(@class, 'tweet-content')]", $tweetElement);
                if ($textNodes->length > 0) {
                    $text = trim($textNodes[0]->textContent);
                }
                
                // Extract tweet URL
                $linkNodes = $xpath->query(".//a[contains(@class, 'tweet-link')]", $tweetElement);
                if ($linkNodes->length > 0) {
                    $tweetUrl = 'https://nitter.net' . $linkNodes[0]->getAttribute('href');
                }
                
                // Extract timestamp
                $timeNodes = $xpath->query(".//span[contains(@class, 'tweet-date')]/a", $tweetElement);
                if ($timeNodes->length > 0) {
                    $timeStr = $timeNodes[0]->getAttribute('title');
                    if (!empty($timeStr)) {
                        $timestamp = date('Y-m-d H:i:s', strtotime($timeStr));
                    }
                }
                
                if ($username && $text) {
                    $posts[] = [
                        'platform' => 'twitter',
                        'id' => 'twitter_' . md5($username . $timestamp . $text),
                        'text' => $text,
                        'created_at' => $timestamp,
                        'author' => [
                            'name' => $username,
                            'username' => $username,
                            'profile_image_url' => 'https://abs.twimg.com/sticky/default_profile_images/default_profile_normal.png'
                        ],
                        'url' => $tweetUrl,
                        'media' => [],
                        'metrics' => [
                            'likes' => 0,
                            'comments' => 0,
                            'shares' => 0
                        ]
                    ];
                }
            } catch (Exception $e) {
                error_log("Error processing tweet: " . $e->getMessage());
                continue; // Skip this tweet if there's an error
            }
        }
        
        return $posts;
    }
}
