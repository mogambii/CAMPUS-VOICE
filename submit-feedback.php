<?php
define('IN_CAMPUS_VOICE', true);
require_once 'includes/functions.php';
require_once 'includes/ai_utils.php';
requireLogin();

// Require login to submit feedback
requireLogin();

$user = getCurrentUser();
$db = getDB();
$aiDetector = new AIDuplicateDetector($db);

// Get all categories in specific order
$categories = [];
$ordered_categories = ['Academic', 'Facilities', 'Security', 'Administration', 'Technical'];

// First, get the ordered categories
foreach ($ordered_categories as $cat_name) {
    $stmt = $db->prepare("SELECT * FROM categories WHERE name = ?");
    $stmt->execute([$cat_name]);
    if ($category = $stmt->fetch()) {
        $categories[] = $category;
    }
}

// Then get any remaining categories not in the ordered list
$placeholders = str_repeat('?,', count($ordered_categories) - 1) . '?';
$stmt = $db->prepare("SELECT * FROM categories WHERE name NOT IN ($placeholders) ORDER BY name");
$stmt->execute($ordered_categories);
$other_categories = $stmt->fetchAll();

// Merge the ordered categories with others
$categories = array_merge($categories, $other_categories);

if (empty($categories)) {
    die("No categories found. Please contact the administrator.");
}

$error = '';
$success = '';
$similarFeedback = [];
$showDuplicateWarning = false;
$formData = [
    'description' => '',
    'category_id' => '',
    'email' => '',
    'is_anonymous' => 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $description = sanitize($_POST['description'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $is_duplicate = isset($_POST['confirm_duplicate']) && $_POST['confirm_duplicate'] === '1';
    $original_feedback_id = isset($_POST['original_feedback_id']) ? intval($_POST['original_feedback_id']) : 0;
    
    // Store form data for repopulation
    $formData = [
        'description' => $description,
        'category_id' => $category_id,
        'email' => !empty($user['email']) ? $user['email'] : filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL),
        'is_anonymous' => isset($_POST['is_anonymous']) ? 1 : 0,
    ];
    
    // Use logged-in user's email if available, otherwise use the one from the form
    $email = $formData['email'];
    $is_anonymous = $formData['is_anonymous'];
    
    // Check for duplicate feedback if not already confirmed
    if (!$is_duplicate && !empty($description) && $category_id > 0) {
        try {
            $similarFeedback = $aiDetector->findSimilarFeedback($description, $category_id);
            if (!empty($similarFeedback)) {
                $showDuplicateWarning = true;
            }
        } catch (Exception $e) {
            error_log('AI Duplicate Detection Error: ' . $e->getMessage());
            // Continue with submission if there's an error with the AI service
        }
    }
    
    // File upload handling
    $file_path = '';
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/feedback/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid() . '.' . $file_extension;
        $target_path = $upload_dir . $file_name;
        
        // Check file type (allow common document and image formats)
        $allowed_types = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif'];
        if (!in_array(strtolower($file_extension), $allowed_types)) {
            $error = 'Invalid file type. Allowed types: ' . implode(', ', $allowed_types);
        } elseif ($_FILES['attachment']['size'] > 5 * 1024 * 1024) { // 5MB max
            $error = 'File is too large. Maximum size is 5MB.';
        } elseif (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_path)) {
            $file_path = $target_path;
        } else {
            $error = 'Error uploading file. Please try again.';
        }
    }
    
    // Basic server-side duplicate detection using similar_text (80% threshold)
    if (empty($error) && !empty($description) && $category_id > 0) {
        try {
            $dupStmt = $db->prepare("SELECT id, description FROM feedback WHERE category_id = ?");
            $dupStmt->execute([$category_id]);
            $existingFeedback = $dupStmt->fetchAll();

            foreach ($existingFeedback as $existing) {
                // Use similar_text on lowercased descriptions
                similar_text(mb_strtolower($description), mb_strtolower($existing['description']), $percent);
                if ($percent >= 80) {
                    $error = 'A very similar feedback already exists (ID: ' . (int)$existing['id'] . '). Please check existing feedback before submitting.';
                    break;
                }
            }
        } catch (Exception $e) {
            error_log('Error in basic duplicate detection: ' . $e->getMessage());
        }
    }

    // Only process the form if there are no duplicate warnings or server-side duplicate errors,
    // or if user confirmed to submit anyway
    if (!$showDuplicateWarning && empty($error)) {
        if (empty($description) || $category_id === 0 || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please fill in all required fields';
        } else {
            // Insert feedback with title and proper status
            $title = substr(trim($_POST['description']), 0, 100); // Create a title from the first 100 chars
            $status = 'pending'; // Default status for new feedback
            
            $insert_stmt = $db->prepare("
               INSERT INTO feedback (user_id, category_id, title, description, email, file_path, is_anonymous, status)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            // Execute with all parameters including title and status
            $success = $insert_stmt->execute([
                $user['id'],
                $_POST['category_id'],
                $title,
                $_POST['description'],
                $_POST['email'],
                $file_path ?? null,
                isset($_POST['is_anonymous']) ? 1 : 0,
                $status
            ]);
            
            if ($success) {
                $feedback_id = $db->lastInsertId();
                logActivity($user['id'], 'submit_feedback', 'feedback', $feedback_id);
                
                // Generate and store embedding for the new feedback
                try {
                    $aiDetector->storeEmbedding($feedback_id, $aiDetector->generateEmbedding($description));
                } catch (Exception $e) {
                    error_log('Error generating embedding for feedback #' . $feedback_id . ': ' . $e->getMessage());
                }
                
                $success = 'Feedback submitted successfully!';
                
                // Clear form data
                $formData = [
                    'description' => '',
                    'category_id' => '',
                    'email' => $email, // Keep the email
                    'is_anonymous' => 0,
                ];
                
                // Redirect to view feedback
                header('Location: view-feedback.php?id=' . $feedback_id);
                exit();
            } else {
                $error = 'Failed to submit feedback. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Feedback Chatbot Dependencies -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/feedback-chatbot.css" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Feedback - Campus Voice</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="admin/index.php">
                <i class="fas fa-comment-dots me-2"></i>Campus Voice
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($user['first_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="dashboard.php"><i class="fas fa-home me-2"></i>Dashboard</a></li>
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="row justify-content-center">
            <!-- Main Content - Full Width -->
            <div class="col-12 col-lg-10">
                <div class="mb-4">
                    <h2><i class="fas fa-plus-circle me-2"></i>Submit Feedback</h2>
                    <p class="text-muted">Share your concerns, suggestions, or issues with the campus administration.</p>
                </div>

                <div class="row justify-content-center">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <?php if ($error): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>

                                <div id="similarFeedbackAlert" class="alert alert-warning" style="display: none;">
                                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Similar Feedback Found</h6>
                                    <p class="mb-2">We found similar feedback that has already been submitted:</p>
                                    <div id="similarFeedbackList"></div>
                                    <button type="button" class="btn btn-sm btn-warning mt-2" onclick="document.getElementById('similarFeedbackAlert').style.display='none'">
                                        Continue Anyway
                                    </button>
                                </div>

                                <form method="POST" action="" id="feedbackForm" enctype="multipart/form-data">
                                    <!-- Email field at the top -->
                                    <div class="mb-4">
                                        <label for="email" class="form-label">Email Address *</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo isset($user['email']) ? htmlspecialchars($user['email']) : ''; ?>"
                                               <?php echo empty($user['email']) ? 'required' : 'readonly'; ?>>
                                        <?php if (!empty($user['email'])): ?>
                                            <div class="form-text">Logged in as: <?php echo htmlspecialchars($user['email']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    

                                    <div class="mb-3">
                                        <label for="category_id" class="form-label">Category *</label>
                                        <select class="form-select" id="category_id" name="category_id" required>
                                            <option value="">Select a category</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?php echo $category['id']; ?>">
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description *</label>
                                        <textarea class="form-control" id="description" name="description" rows="5" 
                                                  required placeholder="Please provide detailed feedback"></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="attachment" class="form-label">Attachment (Optional)</label>
                                        <input class="form-control" type="file" id="attachment" name="attachment">
                                        <div class="form-text">Supported formats: PDF, DOC, DOCX, JPG, PNG, GIF (Max 5MB)</div>
                                    </div>

                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="is_anonymous" name="is_anonymous">
                                        <label class="form-check-label" for="is_anonymous">
                                            Submit anonymously
                                            <small class="text-muted">(Your identity will be hidden from other users)</small>
                                        </label>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                            <i class="fas fa-paper-plane me-2"></i>Submit Feedback
                                        </button>
                                        <a href="admin/index.php" class="btn btn-outline-secondary">Cancel</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <!-- Feedback Chatbot Script -->
    <script src="assets/js/feedback-chatbot.js"></script>
</body>
</html>
