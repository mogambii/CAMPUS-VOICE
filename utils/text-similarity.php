<?php
/**
 * Text Similarity Utility
 * 
 * Provides functions to calculate text similarity between strings using various algorithms.
 * Includes text normalization, tokenization, and similarity scoring.
 */

class TextSimilarity {
    // Common English stop words to filter out
    private static $stopWords = [
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'but', 'by', 'for', 'if', 'in', 
        'into', 'is', 'it', 'no', 'not', 'of', 'on', 'or', 'such', 'that', 'the', 
        'their', 'then', 'there', 'these', 'they', 'this', 'to', 'was', 'will', 'with',
        'i', 'me', 'my', 'myself', 'we', 'our', 'ours', 'you', 'your', 'yours',
        'he', 'him', 'his', 'she', 'her', 'hers', 'its', 'they', 'them', 'theirs'
    ];

    /**
     * Normalize text by converting to lowercase, removing punctuation, and extra whitespace
     * 
     * @param string $text The input text to normalize
     * @return string Normalized text
     */
    public static function normalizeText(string $text): string {
        // Convert to lowercase
        $text = mb_strtolower($text, 'UTF-8');
        
        // Remove URLs
        $text = preg_replace('@https?://\S+@', '', $text);
        
        // Remove punctuation and special characters (keep alphanumeric and spaces)
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        
        // Replace multiple spaces with single space
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Trim whitespace
        return trim($text);
    }

    /**
     * Tokenize text into words and remove stop words
     * 
     * @param string $text The input text to tokenize
     * @return array Array of tokens (words)
     */
    public static function tokenize(string $text): array {
        $text = self::normalizeText($text);
        $words = preg_split('/\s+/', $text);
        
        // Remove stop words and empty tokens
        return array_filter($words, function($word) {
            return !in_array($word, self::$stopWords) && strlen($word) > 2;
        });
    }

    /**
     * Calculate Jaccard similarity between two texts
     * Jaccard similarity = |A ∩ B| / |A ∪ B|
     * 
     * @param string $text1 First text
     * @param string $text2 Second text
     * @return float Similarity score between 0 and 1
     */
    public static function jaccardSimilarity(string $text1, string $text2): float {
        $tokens1 = self::tokenize($text1);
        $tokens2 = self::tokenize($text2);
        
        if (empty($tokens1) && empty($tokens2)) {
            return 1.0; // Both texts are empty or contain only stop words
        }
        
        $intersection = array_intersect($tokens1, $tokens2);
        $union = array_unique(array_merge($tokens1, $tokens2));
        
        return count($intersection) / count($union);
    }

    /**
     * Calculate Cosine similarity between two texts using TF-IDF
     * 
     * @param string $text1 First text
     * @param string $text2 Second text
     * @return float Similarity score between 0 and 1
     */
    public static function cosineSimilarity(string $text1, string $text2): float {
        $tokens1 = self::tokenize($text1);
        $tokens2 = self::tokenize($text2);
        
        if (empty($tokens1) && empty($tokens2)) {
            return 1.0; // Both texts are empty or contain only stop words
        }
        
        // Get term frequencies for both texts
        $tf1 = array_count_values($tokens1);
        $tf2 = array_count_values($tokens2);
        
        // Get all unique terms
        $allTerms = array_unique(array_merge($tokens1, $tokens2));
        
        $dotProduct = 0;
        $magnitude1 = 0;
        $magnitude2 = 0;
        
        foreach ($allTerms as $term) {
            $count1 = $tf1[$term] ?? 0;
            $count2 = $tf2[$term] ?? 0;
            
            $dotProduct += $count1 * $count2;
            $magnitude1 += $count1 * $count1;
            $magnitude2 += $count2 * $count2;
        }
        
        $magnitude = sqrt($magnitude1) * sqrt($magnitude2);
        
        return $magnitude > 0 ? $dotProduct / $magnitude : 0;
    }

    /**
     * Calculate combined similarity score using multiple methods
     * 
     * @param string $text1 First text
     * @param string $text2 Second text
     * @return float Combined similarity score between 0 and 1
     */
    public static function combinedSimilarity(string $text1, string $text2): float {
        $jaccard = self::jaccardSimilarity($text1, $text2);
        $cosine = self::cosineSimilarity($text1, $text2);
        
        // Simple average of both methods
        // You can adjust weights if one method works better for your use case
        return ($jaccard + $cosine) / 2;
    }

    /**
     * Find the most similar text from a list of candidates
     * 
     * @param string $text The text to compare against
     * @param array $candidates Array of candidate texts
     * @param int $limit Maximum number of results to return (0 for all)
     * @param float $minScore Minimum similarity score (0-1) to include in results
     * @return array Array of ['text' => string, 'score' => float] sorted by score descending
     */
    public static function findMostSimilar(
        string $text, 
        array $candidates, 
        int $limit = 3, 
        float $minScore = 0.3
    ): array {
        $scores = [];
        
        foreach ($candidates as $candidate) {
            $score = self::combinedSimilarity($text, $candidate);
            if ($score >= $minScore) {
                $scores[] = [
                    'text' => $candidate,
                    'score' => $score
                ];
            }
        }
        
        // Sort by score descending
        usort($scores, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        // Apply limit
        if ($limit > 0) {
            $scores = array_slice($scores, 0, $limit);
        }
        
        return $scores;
    }
}

// Example usage:
/*
$text1 = "How do I reset my password?";
$text2 = "I forgot my password and need to reset it";
$text3 = "How can I change my email address?";

$similarity = TextSimilarity::combinedSimilarity($text1, $text2);
echo "Similarity between text1 and text2: " . number_format($similarity * 100, 2) . "%\n";

$similarity = TextSimilarity::combinedSimilarity($text1, $text3);
echo "Similarity between text1 and text3: " . number_format($similarity * 100, 2) . "%\n";
*/
?>
