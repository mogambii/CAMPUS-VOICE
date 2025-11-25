<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/config.php';

class AIDuplicateDetector {
    private $db;
    private $similarityThreshold = 0.82; // Higher threshold for OpenAI embeddings
    private $apiKey;
    private $model;
    
    public function __construct($db) {
        $this->db = $db;
        $this->apiKey = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '';
        $this->model = defined('OPENAI_EMBEDDING_MODEL') ? OPENAI_EMBEDDING_MODEL : 'text-embedding-3-small';
        
        if (empty($this->apiKey)) {
            throw new Exception('OpenAI API key is not configured');
        }
    }
    
    /**
     * Generate embedding for the given text using OpenAI API
     * @param string $text The text to generate embedding for
     * @return array The embedding vector
     * @throws Exception If there's an error generating the embedding
     */
    public function generateEmbedding($text) {
        if (empty(trim($text))) {
            throw new Exception('Text cannot be empty');
        }
        
        $url = 'https://api.openai.com/v1/embeddings';
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
        ];
        
        $data = [
            'input' => $text,
            'model' => $this->model,
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            error_log('OpenAI API Error: ' . curl_error($ch));
            throw new Exception('Failed to connect to OpenAI API');
        }
        
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if ($httpCode !== 200) {
            $error = $result['error']['message'] ?? 'Unknown error';
            error_log('OpenAI API Error: ' . $error);
            throw new Exception('OpenAI API error: ' . $error);
        }
        
        return $result['data'][0]['embedding'] ?? [];
    }
    
    /**
     * Calculate cosine similarity between two embeddings
     */
    private function cosineSimilarity($vec1, $vec2) {
        if (empty($vec1) || empty($vec2)) {
            return 0;
        }
        
        // Ensure both vectors have the same length
        $dimensions = min(count($vec1), count($vec2));
        $dotProduct = 0;
        $magnitude1 = 0;
        $magnitude2 = 0;
        
        for ($i = 0; $i < $dimensions; $i++) {
            $dotProduct += $vec1[$i] * $vec2[$i];
            $magnitude1 += $vec1[$i] * $vec1[$i];
            $magnitude2 += $vec2[$i] * $vec2[$i];
        }
        
        $magnitude1 = sqrt($magnitude1);
        $magnitude2 = sqrt($magnitude2);
        
        if ($magnitude1 == 0 || $magnitude2 == 0) {
            return 0;
        }
        
        $similarity = $dotProduct / ($magnitude1 * $magnitude2);
        
        // Normalize to 0-1 range (just in case of floating point errors)
        return max(0, min(1, $similarity));
    }
    
    /**
     * Find similar feedback using embedding similarity
     * @param string $text The text to find similar feedback for
     * @param int|null $categoryId Optional category ID to filter by
     * @param int $limit Maximum number of similar feedback to return
     * @param float $similarityThreshold Minimum similarity score (0-1) to consider as similar
     * @return array Array of similar feedback with similarity scores
     */
    public function findSimilarFeedback($text, $categoryId = null, $limit = 5, $similarityThreshold = 0.8) {
        try {
            // Generate embedding for the new text
            $embedding = $this->generateEmbedding($text);
            $similarFeedback = [];
            
            // Get all feedback to compare against
            $query = "SELECT f.*, c.name as category_name, fe.embedding, f.resolution, 
                     (SELECT COUNT(*) FROM feedback_responses fr WHERE fr.feedback_id = f.id) as response_count,
                     (SELECT COUNT(*) FROM feedback_votes fv WHERE fv.feedback_id = f.id) as vote_count
                     FROM feedback f 
                     LEFT JOIN categories c ON f.category_id = c.id 
                     LEFT JOIN feedback_embeddings fe ON f.id = fe.feedback_id
                     WHERE f.status != 'resolved' AND f.duplicate_of IS NULL";
            
            if ($categoryId) {
                $query .= " AND f.category_id = :category_id";
            }
            
            $stmt = $this->db->prepare($query);
            
            if ($categoryId) {
                $stmt->bindParam(':category_id', $categoryId, PDO::PARAM_INT);
            }
            
            $stmt->execute();
            
            while ($feedback = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $existingEmbedding = $feedback['embedding'];
                
                // If no embedding exists, generate and store one
                if (empty($existingEmbedding)) {
                    try {
                        $existingEmbedding = $this->generateEmbedding($feedback['description']);
                        $this->storeEmbedding($feedback['id'], $existingEmbedding);
                    } catch (Exception $e) {
                        error_log('Error generating embedding for feedback #' . $feedback['id'] . ': ' . $e->getMessage());
                        continue; // Skip this feedback if we can't generate an embedding
                    }
                } else {
                    $existingEmbedding = json_decode($existingEmbedding, true);
                }
                
                // Calculate similarity
                $similarity = $this->cosineSimilarity($embedding, $existingEmbedding);
                
                if ($similarity >= $this->similarityThreshold) {
                    $feedback['similarity'] = $similarity;
                    $similarFeedback[] = $feedback;
                }
            }
            
            // Sort by adjusted similarity (highest first)
            usort($similarFeedback, function($a, $b) {
                return $b['similarity'] <=> $a['similarity'];
            });
            
            // Limit results and format output
            $result = array_slice($similarFeedback, 0, $limit);
            
            // Add debug info if needed
            if (count($result) > 0) {
                error_log('Found ' . count($result) . ' similar feedback items for text: ' . substr($text, 0, 100) . '...');
                foreach ($result as $item) {
                    error_log(sprintf(
                        '- ID: %d, Similarity: %.2f, Status: %s',
                        $item['id'],
                        $item['similarity'] * 100,
                        $item['status']
                    ));
                }
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log('Error in findSimilarFeedback: ' . $e->getMessage());
            return []; // Return empty array on error
        }
    }
    
    /**
     * Store embedding in the database with error handling
     * @param int $feedbackId The ID of the feedback
     * @param array $embedding The embedding vector to store
     * @return bool True on success, false on failure
     */
    public function storeEmbedding($feedbackId, $embedding) {
        try {
            // Check if embedding already exists
            $checkStmt = $this->db->prepare("SELECT id FROM feedback_embeddings WHERE feedback_id = ?");
            $checkStmt->execute([$feedbackId]);
            
            if ($checkStmt->rowCount() > 0) {
                // Update existing
                $stmt = $this->db->prepare("UPDATE feedback_embeddings SET embedding = ?, updated_at = NOW() WHERE feedback_id = ?");
                return $stmt->execute([json_encode($embedding), $feedbackId]);
            } else {
                // Insert new
                $stmt = $this->db->prepare("INSERT INTO feedback_embeddings (feedback_id, embedding, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
                return $stmt->execute([$feedbackId, json_encode($embedding)]);
            }
        } catch (Exception $e) {
            error_log('Error storing embedding: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get stored embedding from the database with error handling
     */
    private function getStoredEmbedding($feedbackId) {
        try {
            $stmt = $this->db->prepare("SELECT embedding FROM feedback_embeddings WHERE feedback_id = ?");
            $stmt->execute([$feedbackId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['embedding'] : null;
        } catch (Exception $e) {
            error_log('Error retrieving embedding: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Generate and store embedding for a feedback
     */
    public function generateAndStoreEmbedding($feedbackId, $text) {
        try {
            $embedding = $this->generateEmbedding($text);
            return $this->storeEmbedding($feedbackId, $embedding);
        } catch (Exception $e) {
            error_log("Error in generateAndStoreEmbedding: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark a feedback as a duplicate of another and copy relevant data
     */
    public function markAsDuplicate($duplicateId, $originalId) {
        // Start transaction
        $this->db->beginTransaction();
        
        try {
            // 1. Mark as duplicate
            $stmt = $this->db->prepare("UPDATE feedback SET duplicate_of = ? WHERE id = ?");
            $stmt->execute([$originalId, $duplicateId]);
            
            // 2. Copy votes from duplicate to original
            $this->db->exec("
                INSERT INTO feedback_votes (feedback_id, user_id, created_at)
                SELECT $originalId, user_id, created_at
                FROM feedback_votes
                WHERE feedback_id = $duplicateId
                AND user_id NOT IN (
                    SELECT user_id FROM feedback_votes WHERE feedback_id = $originalId
                )
            ");
            
            // 3. Copy responses from duplicate to original
            $this->db->exec("
                INSERT INTO feedback_responses (feedback_id, user_id, response, created_at)
                SELECT $originalId, user_id, response, created_at
                FROM feedback_responses
                WHERE feedback_id = $duplicateId
                AND id NOT IN (
                    SELECT id FROM feedback_responses 
                    WHERE feedback_id = $originalId 
                    AND response IN (
                        SELECT response FROM feedback_responses 
                        WHERE feedback_id = $duplicateId
                    )
                )
            ");
            
            // 4. Update status if original is resolved
            $originalStatus = $this->db->query("SELECT status FROM feedback WHERE id = $originalId")->fetchColumn();
            if ($originalStatus === 'resolved') {
                $this->db->exec("UPDATE feedback SET status = 'resolved' WHERE id = $duplicateId");
                
                // Copy resolution details
                $resolution = $this->db->query("SELECT resolution FROM feedback WHERE id = $originalId")->fetchColumn();
                $this->db->prepare("UPDATE feedback SET resolution = ? WHERE id = ?")
                    ->execute([$resolution, $duplicateId]);
            }
            
            // 5. Log the action
            $this->db->prepare("
                INSERT INTO activity_log (user_id, action, entity_type, entity_id, details)
                VALUES (?, 'marked_duplicate', 'feedback', ?, ?)
            ")->execute([
                $_SESSION['user_id'] ?? null,
                $duplicateId,
                json_encode(['original_feedback_id' => $originalId])
            ]);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('Error marking feedback as duplicate: ' . $e->getMessage());
            return false;
        }
        $stmt = $this->db->prepare("UPDATE feedback SET duplicate_of = ? WHERE id = ?");
        return $stmt->execute([$originalId, $duplicateId]);
    }
}
?>
