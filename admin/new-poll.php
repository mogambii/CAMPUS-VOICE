<?php
// Define constant to prevent direct access
define('IN_CAMPUS_VOICE', true);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Check if user is logged in as admin
requireAdmin();

$db = getDB();
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $question = trim($_POST['question']);
        $description = trim($_POST['description']);
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        
        // Determine status based on dates
        $now = date('Y-m-d H:i:s');
        $status = 'active'; // Default to active if no dates are set
        
        if ($start_date && strtotime($start_date) > time()) {
            $status = 'upcoming';
        } elseif ($end_date && strtotime($end_date) < time()) {
            $status = 'ended';
        }
        $options = array_filter($_POST['options'], function($option) {
            return !empty(trim($option));
        });
        $allow_multiple = isset($_POST['allow_multiple']) ? 1 : 0;
        $show_results = isset($_POST['show_results']) ? 1 : 0;
        $anonymous_voting = isset($_POST['anonymous_voting']) ? 1 : 0;
        
        // Validation
        if (empty($question)) {
            throw new Exception("Question is required");
        }
        
        if (count($options) < 2) {
            throw new Exception("At least two options are required");
        }
        
        if ($start_date && $end_date && strtotime($start_date) > strtotime($end_date)) {
            throw new Exception("End date must be after start date");
        }
        
        // Start transaction
        $db->beginTransaction();
        
        // Prepare options as JSON
        $options_json = json_encode($options);
        
        // Insert poll - using the correct table structure
        $stmt = $db->prepare("
            INSERT INTO polls (question, description, status, start_date, end_date, allow_multiple, show_results, anonymous_voting, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $question,
            $description,
            $status,
            $start_date,
            $end_date,
            $allow_multiple,
            $show_results,
            $anonymous_voting,
            $_SESSION['user_id'] ?? 1  // Using created_by instead of user_id
        ]);
        
        $poll_id = $db->lastInsertId();
        
        // Insert options
        $stmt = $db->prepare("INSERT INTO poll_options (poll_id, option_text) VALUES (?, ?)");
        foreach ($options as $option_text) {
            $stmt->execute([$poll_id, trim($option_text)]);
        }
        
        // Commit transaction
        $db->commit();
        
        $_SESSION['success'] = "Poll created successfully!";
        header("Location: polls.php");
        exit;
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Poll - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card {
            box-shadow: none;
            border-radius: 0 !important;
            border: none !important;
            margin: 0;
            height: 100%;
        }
        .card-header {
            border-radius: 0 !important;
            border-bottom: 1px solid #dee2e6 !important;
            padding: 1.25rem 2rem 1.25rem 4rem;
        }
        
        @media (min-width: 768px) and (max-width: 991.98px) {
            .card-header {
                padding-left: 4rem;
            }
        }
        .option-row .input-group-text {
            min-width: 45px;
            justify-content: center;
        }
        .remove-option {
            transition: all 0.2s;
        }
        .remove-option:hover {
            background-color: #dc3545;
            border-color: #dc3545;
            color: white;
        }
        .preview-card {
            border: 1px dashed #dee2e6;
            border-radius: 0.375rem;
            padding: 1.5rem;
            margin-top: 2rem;
            background-color: #f8f9fa;
        }
        .preview-option {
            margin-bottom: 0.5rem;
            padding: 0.5rem;
            background: white;
            border-radius: 0.25rem;
            border: 1px solid #dee2e6;
        }
    </style>
</head>
<body class="admin-dashboard">
    <!-- Main Wrapper -->
    <div class="container-fluid p-0">
        <div class="d-flex">
            <!-- Sidebar Toggle Button (Always Visible) -->
            <button class="btn btn-link text-primary d-lg-none position-fixed" type="button" id="sidebarToggle" style="z-index: 1000; left: 10px; top: 20px;">
                <i class="fas fa-bars fa-lg"></i>
            </button>
            
            <!-- Sidebar (Hidden by default on mobile) -->
            <div id="sidebarWrapper" class="d-none d-lg-block">
                <?php include 'includes/admin_sidebar.php'; ?>
            </div>
            
            <!-- Main Content -->
            <div class="flex-grow-1 d-flex flex-column" style="min-height: 100vh; margin-left: 0;">

                <!-- Main Content - Full Width -->
                <div class="flex-grow-1 p-0">
                    <div class="w-100">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['success']; 
                        unset($_SESSION['success']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card shadow-sm">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom">
                                <h5 class="mb-0">Create New Poll</h5>
                                <div>
                                    <a href="polls.php" class="btn btn-sm btn-outline-secondary me-2">
                                        <i class="fas fa-list me-1"></i> View All Polls
                                    </a>
                                    <a href="index.php" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                                    </a>
                                </div>
                            </div>
                            <div class="card-body p-4">
                                <form id="pollForm" method="POST" action="">
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="mb-3">
                                                <label for="question" class="form-label">Question <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control form-control-lg" id="question" name="question" required 
                                                       value="<?= htmlspecialchars($_POST['question'] ?? '') ?>">
                                                <div class="form-text">Enter the poll question that users will see.</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="mb-4">
                                                <label for="description" class="form-label">Description</label>
                                                <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                                                <div class="form-text">Optional. Provide more context about this poll.</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row g-3 mb-4">
                                        <div class="col-md-6">
                                            <div class="card h-100 border-0 bg-light">
                                                <div class="card-body p-4">
                                                    <h6 class="card-title">Voting Options</h6>
                                                    <div class="form-check form-switch mb-2">
                                                        <input class="form-check-input" type="checkbox" id="allow_multiple" name="allow_multiple" value="1" <?= isset($_POST['allow_multiple']) ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="allow_multiple">
                                                            Allow multiple selections
                                                        </label>
                                                    </div>
                                                    <div class="form-check form-switch mb-2">
                                                        <input class="form-check-input" type="checkbox" id="anonymous_voting" name="anonymous_voting" value="1" <?= isset($_POST['anonymous_voting']) ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="anonymous_voting">
                                                            Anonymous voting
                                                        </label>
                                                    </div>
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" id="show_results" name="show_results" value="1" <?= !isset($_POST['show_results']) || $_POST['show_results'] ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="show_results">
                                                            Show results to voters
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="card h-100 border-0 bg-light">
                                                <div class="card-body p-4">
                                                    <h6 class="card-title">Scheduling</h6>
                                                    <div class="mb-3">
                                                        <label for="start_date" class="form-label small mb-1">Start Date</label>
                                                        <input type="datetime-local" class="form-control form-control-sm" id="start_date" name="start_date" 
                                                               value="<?= htmlspecialchars($_POST['start_date'] ?? '') ?>">
                                                        <div class="form-text small">Leave empty to start immediately</div>
                                                    </div>
                                                    <div>
                                                        <label for="end_date" class="form-label small mb-1">End Date</label>
                                                        <input type="datetime-local" class="form-control form-control-sm" id="end_date" name="end_date"
                                                               value="<?= htmlspecialchars($_POST['end_date'] ?? '') ?>">
                                                        <div class="form-text small">Leave empty for no end date</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <label class="form-label mb-0">Poll Options <span class="text-danger">*</span></label>
                                            <small class="text-muted">At least 2 required</small>
                                        </div>
                                        
                                        <div id="options-container" class="mb-3">
                                            <?php 
                                            $saved_options = !empty($_POST['options']) ? array_values(array_filter($_POST['options'])) : ['', ''];
                                            foreach ($saved_options as $index => $option): 
                                            ?>
                                            <div class="option-row mb-2">
                                                <div class="input-group">
                                                    <span class="input-group-text bg-light"><?= $index + 1 ?></span>
                                                    <input type="text" class="form-control" name="options[]" 
                                                           value="<?= htmlspecialchars($option) ?>" 
                                                           placeholder="Enter option text"
                                                           <?= $index < 2 ? 'required' : '' ?>>
                                                    <?php if ($index >= 2): ?>
                                                        <button type="button" class="btn btn-outline-danger remove-option" onclick="removeOption(this)">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="addOption()">
                                            <i class="fas fa-plus me-1"></i> Add Another Option
                                        </button>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center mt-5 pt-3 border-top">
                                        <a href="polls.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-arrow-left me-1"></i> Back to Polls
                                        </a>
                                        <div class="d-flex">
                                            <button type="submit" name="save_draft" value="1" class="btn btn-outline-secondary me-2">
                                                <i class="far fa-save me-1"></i> Save as Draft
                                            </button>
                                            <button type="submit" class="btn btn-primary px-4">
                                                <i class="fas fa-check me-1"></i> Publish Poll
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                    </div>
                </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Toggle sidebar on mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebarWrapper');
            if (sidebar.classList.contains('d-none')) {
                sidebar.classList.remove('d-none');
                sidebar.classList.add('position-fixed', 'shadow-lg', 'h-100');
                sidebar.style.zIndex = '1050';
                sidebar.style.width = '250px';
            } else {
                sidebar.classList.add('d-none');
            }
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebarWrapper');
            const toggleBtn = document.getElementById('sidebarToggle');
            if (window.innerWidth < 992 && !sidebar.contains(e.target) && e.target !== toggleBtn && !toggleBtn.contains(e.target)) {
                sidebar.classList.add('d-none');
            }
        });
    </script>
    <script>
        // Initialize date pickers
        flatpickr("input[type=datetime-local]", {
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            time_24hr: true
        });
        
        // Toggle sidebar on mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.admin-dashboard').classList.toggle('sidebar-toggled');
        });
        
        // Add new option
        function addOption() {
            const container = document.getElementById('options-container');
            const optionCount = container.getElementsByClassName('option-row').length;
            
            const optionRow = document.createElement('div');
            optionRow.className = 'option-row';
            optionRow.innerHTML = `
                <div class="input-group mb-2">
                    <span class="input-group-text">${optionCount + 1}</span>
                    <input type="text" class="form-control" name="options[]" required>
                </div>
                <span class="remove-option" onclick="removeOption(this)">
                    <i class="fas fa-times"></i>
                </span>
            `;
            
            container.appendChild(optionRow);
            updatePreview();
        }
        
        // Remove option
        function removeOption(button) {
            const row = button.closest('.option-row');
            if (row) {
                row.remove();
                updatePreview();
                // Renumber remaining options
                const inputs = document.querySelectorAll('#options-container .input-group-text');
                inputs.forEach((span, index) => {
                    span.textContent = index + 1;
                });
            }
        }
        
        // Function to validate form before submission
        function validateForm() {
            const options = Array.from(document.querySelectorAll('input[name="options[]"]'))
                .map(input => input.value.trim())
                .filter(Boolean);
                
            if (options.length < 2) {
                alert('Please add at least two options for your poll.');
                return false;
            }
            
            const question = document.getElementById('question').value.trim();
            if (!question) {
                alert('Please enter a question for your poll.');
                return false;
            }
            
            return true;
        }
        
        // Form validation
        document.getElementById('pollForm').addEventListener('submit', function(e) {
            return validateForm();
        });
    </script>
</body>
</html>
