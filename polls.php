<?php
define('IN_CAMPUS_VOICE', true);
require_once 'includes/functions.php';
requireLogin();

$user = getCurrentUser();
$db = getDB();
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_poll'])) {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $options = array_filter(array_map('trim', $_POST['options'] ?? []), 'strlen');
        $end_date = $_POST['end_date'] ?? '';
        $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
        
        if (empty($title) || empty($options) || count($options) < 2) {
            $error = 'Please provide a title and at least two options for the poll.';
        } else {
            try {
                $db->beginTransaction();
                
                // Insert poll
                $stmt = $db->prepare("
                    INSERT INTO polls (question, description, status, start_date, end_date, created_at, created_by, allow_multiple, show_results, anonymous_voting)
                    VALUES (?, ?, 'active', NOW(), ?, NOW(), ?, 0, 1, ?)
                ");
                $stmt->execute([$title, $description, $end_date, $user['id'], $is_anonymous]);
                $poll_id = $db->lastInsertId();
                
                // Insert options
                $optionStmt = $db->prepare("
                    INSERT INTO poll_options (poll_id, option_text, created_at)
                    VALUES (?, ?, NOW())
                ");
                
                foreach ($options as $option) {
                    if (!empty($option)) {
                        $optionStmt->execute([$poll_id, $option]);
                    }
                }
                
                $db->commit();
                $success = 'Poll created successfully!';
                
                // Clear form
                $_POST = [];
                
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Error creating poll: ' . $e->getMessage();
            }
        }
    }
}

// Get active polls
$activePolls = [];
$inactivePolls = [];

try {
    // Get polls with vote counts and user's vote status
    $pollsQuery = "
        SELECT 
            p.*, 
            u.email as creator_name,
            COUNT(DISTINCT v.id) as total_votes,
            (SELECT COUNT(*) FROM poll_votes pv WHERE pv.poll_id = p.id AND pv.user_id = ?) as has_voted,
            (SELECT COUNT(*) FROM poll_options po WHERE po.poll_id = p.id) as option_count
        FROM polls p
        LEFT JOIN users u ON p.created_by = u.id
        LEFT JOIN poll_votes v ON p.id = v.poll_id
        WHERE p.end_date > NOW() OR p.end_date IS NULL
        GROUP BY p.id
        ORDER BY p.created_at DESC
    ";
    
    $stmt = $db->prepare($pollsQuery);
    $stmt->execute([$user['id']]);
    $activePolls = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get inactive (past) polls
    $inactiveStmt = $db->prepare("
        SELECT 
            p.*, 
            u.email as creator_name,
            COUNT(DISTINCT v.id) as total_votes,
            (SELECT COUNT(*) FROM poll_votes pv WHERE pv.poll_id = p.id AND pv.user_id = ?) as has_voted,
            (SELECT COUNT(*) FROM poll_options po WHERE po.poll_id = p.id) as option_count
        FROM polls p
        LEFT JOIN users u ON p.created_by = u.id
        LEFT JOIN poll_votes v ON p.id = v.poll_id
        WHERE p.end_date <= NOW()
        GROUP BY p.id
        ORDER BY p.end_date DESC
    ");
    $inactiveStmt->execute([$user['id']]);
    $inactivePolls = $inactiveStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = 'Error loading polls: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Polls & Surveys - Campus Voice</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .poll-card {
            transition: transform 0.2s;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
            color: #000000; /* Ensure text is black */
        }
        
        .poll-card .card-title {
            color: #000000 !important;
        }
        
        .poll-card .text-muted {
            color: #495057 !important;
        }
        
        .poll-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .poll-header {
            background-color: #f8f9fa;
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        .poll-options {
            padding: 15px;
        }
        .progress {
            height: 25px;
            margin-bottom: 10px;
        }
        .progress-bar {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 10px;
            font-weight: 500;
        }
        .nav-tabs .nav-link {
            color: #495057;
            font-weight: 500;
        }
        .nav-tabs .nav-link.active {
            font-weight: 600;
            border-bottom: 3px solid #0d6efd;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="mb-4">
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-poll me-2"></i>Polls & Surveys</h2>
            <?php if (isAdmin()): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPollModal">
                    <i class="fas fa-plus me-2"></i>Create Poll
                </button>
            <?php endif; ?>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <ul class="nav nav-tabs mb-4" id="pollsTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="active-tab" data-bs-toggle="tab" data-bs-target="#active" type="button" role="tab" aria-controls="active" aria-selected="true" style="color: #000000 !important;">
                    <i class="fas fa-poll me-1"></i> Active Polls <span class="badge bg-primary ms-2"><?php echo count($activePolls); ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="inactive-tab" data-bs-toggle="tab" data-bs-target="#inactive" type="button" role="tab" aria-controls="inactive" aria-selected="false" style="color: #000000 !important;">
                    <i class="fas fa-archive me-1"></i> Past Polls <span class="badge bg-secondary ms-2"><?php echo count($inactivePolls); ?></span>
                </button>
            </li>
        </ul>

        <div class="tab-content" id="pollsTabContent">
            <!-- Active Polls Tab -->
            <div class="tab-pane fade show active" id="active" role="tabpanel" aria-labelledby="active-tab">
                <?php if (empty($activePolls)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-clipboard-question fa-4x text-muted mb-3"></i>
                        <h4>No active polls at the moment</h4>
                        <p class="text-muted">No polls available at the moment.</p>
                        <?php if (isAdmin()): ?>
                            <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#createPollModal">
                                <i class="fas fa-plus me-2"></i>Create Poll
                            </button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($activePolls as $poll): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card poll-card h-100">
                                    <div class="card-body" style="color: #000000;">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h5 class="card-title mb-1" style="color: #000000 !important;"><?php echo htmlspecialchars($poll['question']); ?></h5>
                                            <span class="badge bg-<?php echo $poll['has_voted'] ? 'success' : 'warning'; ?> text-white">
                                                <?php echo $poll['has_voted'] ? 'Voted' : 'Vote Now'; ?>
                                            </span>
                                        </div>
                                        
                                        <?php if (!empty($poll['description'])): ?>
                                            <p class="text-muted small mb-3"><?php echo nl2br(htmlspecialchars($poll['description'])); ?></p>
                                        <?php endif; ?>
                                        
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between">
                                                <small class="text-muted">
                                                    <?php echo $poll['total_votes']; ?> votes
                                                </small>
                                                <small class="text-muted">
                                                    <?php echo $poll['anonymous_voting'] ? 'Anonymous' : 'By ' . htmlspecialchars($poll['creator_name']); ?>
                                                </small>
                                            </div>
                                            <?php if ($poll['end_date']): ?>
                                                <small class="text-muted d-block">
                                                    <i class="far fa-clock me-1"></i> 
                                                    Ends on <?php echo date('M j, Y', strtotime($poll['end_date'])); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($poll['has_voted']): ?>
                                            <div class="alert alert-success text-center py-2 mb-0">
                                                <i class="fas fa-check-circle me-2"></i> You have voted
                                            </div>
                                        <?php else: 
                                            // Get poll options
                                            $optionsStmt = $db->prepare("SELECT id, option_text FROM poll_options WHERE poll_id = ?");
                                            $optionsStmt->execute([$poll['id']]);
                                            $options = $optionsStmt->fetchAll();
                                        ?>
                                            <form action="vote.php" method="post" class="vote-form">
                                                <input type="hidden" name="poll_id" value="<?php echo $poll['id']; ?>">
                                                <div class="mb-3">
                                                    <?php foreach ($options as $option): ?>
                                                        <div class="form-check mb-2">
                                                            <input class="form-check-input" type="radio" 
                                                                   name="option_id" 
                                                                   id="option_<?php echo $option['id']; ?>" 
                                                                   value="<?php echo $option['id']; ?>" required>
                                                            <label class="form-check-label" for="option_<?php echo $option['id']; ?>">
                                                                <?php echo htmlspecialchars($option['option_text']); ?>
                                                            </label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <button type="submit" name="vote" class="btn btn-primary w-100">
                                                    Submit Vote
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Inactive Polls Tab -->
            <div class="tab-pane fade" id="inactive" role="tabpanel" aria-labelledby="inactive-tab">
                <?php if (empty($inactivePolls)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-archive fa-4x text-muted mb-3"></i>
                        <h4>No past polls to show</h4>
                        <p class="text-muted">Check back later for completed polls.</p>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($inactivePolls as $poll): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card poll-card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($poll['question']); ?></h5>
                                        
                                        <?php if (!empty($poll['description'])): ?>
                                            <p class="text-muted small mb-3"><?php echo nl2br(htmlspecialchars($poll['description'])); ?></p>
                                        <?php endif; ?>
                                        
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between">
                                                <small class="text-muted">
                                                    <?php echo $poll['total_votes']; ?> total votes
                                                </small>
                                                <small class="text-muted">
                                                    <?php echo $poll['anonymous_voting'] ? 'Anonymous' : 'By ' . htmlspecialchars($poll['creator_name']); ?>
                                                </small>
                                            </div>
                                            <small class="text-muted d-block">
                                                <i class="far fa-calendar-check me-1"></i> 
                                                Ended on <?php echo date('M j, Y', strtotime($poll['end_date'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Create Poll Modal -->
    <div class="modal fade" id="createPollModal" tabindex="-1" aria-labelledby="createPollModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createPollModalLabel"><i class="fas fa-plus-circle me-2"></i>Create New Poll</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="title" class="form-label">Poll Question <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" required 
                                   value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description (Optional)</label>
                            <textarea class="form-control" id="description" name="description" rows="2"><?php 
                                echo htmlspecialchars($_POST['description'] ?? ''); 
                            ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Options <span class="text-danger">*</span> <small class="text-muted">(At least 2 required)</small></label>
                            <div id="poll-options">
                                <?php
                                $options = $_POST['options'] ?? ['', ''];
                                foreach ($options as $index => $option): 
                                ?>
                                    <div class="input-group mb-2">
                                        <span class="input-group-text"><?php echo $index + 1; ?></span>
                                        <input type="text" class="form-control" name="options[]" 
                                               value="<?php echo htmlspecialchars($option); ?>" 
                                               <?php echo $index < 2 ? 'required' : ''; ?>>
                                        <?php if ($index >= 2): ?>
                                            <button type="button" class="btn btn-outline-danger remove-option">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" id="add-option" class="btn btn-sm btn-outline-secondary mt-2">
                                <i class="fas fa-plus me-1"></i> Add Option
                            </button>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="end_date" class="form-label">End Date (Optional)</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date"
                                           min="<?php echo date('Y-m-d'); ?>"
                                           value="<?php echo htmlspecialchars($_POST['end_date'] ?? ''); ?>">
                                    <div class="form-text">Leave empty for no end date</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch mt-4 pt-2">
                                    <input class="form-check-input" type="checkbox" id="is_anonymous" name="is_anonymous" 
                                           <?php echo isset($_POST['is_anonymous']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_anonymous">
                                        Anonymous Poll
                                    </label>
                                    <div class="form-text">Hide voter identities</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_poll" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Create Poll
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add/Remove poll options
        document.addEventListener('DOMContentLoaded', function() {
            const optionsContainer = document.getElementById('poll-options');
            const addOptionBtn = document.getElementById('add-option');
            
            // Add new option
            addOptionBtn.addEventListener('click', function() {
                const optionCount = document.querySelectorAll('#poll-options .input-group').length;
                const newOption = `
                    <div class="input-group mb-2">
                        <span class="input-group-text">${optionCount + 1}</span>
                        <input type="text" class="form-control" name="options[]" required>
                        <button type="button" class="btn btn-outline-danger remove-option">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
                optionsContainer.insertAdjacentHTML('beforeend', newOption);
            });
            
            // Remove option
            optionsContainer.addEventListener('click', function(e) {
                if (e.target.closest('.remove-option')) {
                    const optionGroup = e.target.closest('.input-group');
                    if (optionGroup && document.querySelectorAll('#poll-options .input-group').length > 2) {
                        optionGroup.remove();
                        // Update numbers
                        document.querySelectorAll('#poll-options .input-group-text').forEach((el, index) => {
                            el.textContent = index + 1;
                        });
                    }
                }
            });
            
            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>
