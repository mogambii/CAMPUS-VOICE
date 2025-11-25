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
$status = $_GET['status'] ?? 'active';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Handle poll status change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $poll_id = (int)$_POST['poll_id'];
    $new_status = $_POST['status'];
    
    $update = $db->prepare("UPDATE polls SET status = ?, updated_at = NOW() WHERE id = ?");
    if ($update->execute([$new_status, $poll_id])) {
        $_SESSION['success'] = "Poll status updated successfully!";
        header("Location: " . $_SERVER['PHP_SELF'] . "?status=$status&page=$page");
        exit;
    } else {
        $error = "Failed to update poll status.";
    }
}

// Handle poll deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $poll_id = (int)$_GET['delete'];
    
    // Start transaction
    $db->beginTransaction();
    
    try {
        // Delete votes
        $db->prepare("DELETE FROM poll_votes WHERE option_id IN (SELECT id FROM poll_options WHERE poll_id = ?)")
           ->execute([$poll_id]);
        
        // Delete options
        $db->prepare("DELETE FROM poll_options WHERE poll_id = ?")->execute([$poll_id]);
        
        // Delete poll
        $db->prepare("DELETE FROM polls WHERE id = ?")->execute([$poll_id]);
        
        $db->commit();
        $_SESSION['success'] = "Poll deleted successfully!";
        header("Location: polls.php");
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Failed to delete poll: " . $e->getMessage();
    }
}

// Build where clause for status filter
$where_clause = "WHERE 1=1";
if ($status === 'active') {
    // Show active polls that are not ended and have started
    $where_clause = "WHERE (p.status = 'active' OR p.status = 'upcoming') AND (p.start_date IS NULL OR p.start_date <= CURDATE()) AND (p.end_date IS NULL OR p.end_date >= CURDATE())";
} elseif ($status === 'upcoming') {
    // Show upcoming polls that haven't started yet
    $where_clause = "WHERE (p.status = 'upcoming' OR (p.status = 'active' AND p.start_date > CURDATE())) AND (p.start_date > CURDATE())";
} elseif ($status === 'ended') {
    // Show all polls that have ended, regardless of their status
    $where_clause = "WHERE (p.end_date IS NOT NULL AND p.end_date < CURDATE())";
} elseif ($status === 'inactive') {
    $where_clause = "WHERE p.status = 'draft'";
}

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM polls p $where_clause";
$total_polls = $db->query($count_query)->fetch()['total'];
$total_pages = ceil($total_polls / $per_page);

// Get polls with vote counts
$query = "SELECT 
    p.*, 
    (SELECT COUNT(DISTINCT user_id) FROM poll_votes pv 
     JOIN poll_options po ON pv.option_id = po.id 
     WHERE po.poll_id = p.id) as total_votes,
    (SELECT COUNT(*) FROM poll_options WHERE poll_id = p.id) as option_count
    FROM polls p
    $where_clause
    ORDER BY 
        CASE 
            WHEN p.status = 'active' AND (p.end_date IS NULL OR p.end_date >= CURDATE()) AND (p.start_date IS NULL OR p.start_date <= CURDATE()) THEN 1
            WHEN p.status = 'upcoming' AND p.start_date > CURDATE() THEN 2
            WHEN p.status = 'draft' THEN 3
            ELSE 4
        END,
        p.created_at DESC
    LIMIT :offset, :per_page";

$stmt = $db->prepare($query);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
$stmt->execute();
$polls = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Polls - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }
        .admin-dashboard {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .flex-grow-1 {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            margin-left: 270px; /* Increased from 250px */
            width: calc(100% - 270px); /* Adjusted to match */
            padding-right: 20px; /* Added right padding */
        }
        .container-fluid {
            max-width: 100% !important;
            padding: 0 20px !important;
            margin: 0 !important;
        }
        .flex-grow-1 {
            width: 100%;
            margin: 0;
            padding: 0;
        }
        .poll-card {
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
            border-left: 4px solid #dee2e6;
            width: 100%;
            max-width: none;
        }
        .poll-card:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        .poll-card.active { border-left-color: #198754; }
        .poll-card.upcoming { border-left-color: #0dcaf0; }
        .poll-card.ended { border-left-color: #6c757d; }
        .poll-card.draft { border-left-color: #ffc107; }
        .progress {
            height: 1.5rem;
            font-size: 0.9rem;
        }
        .progress-bar {
            display: flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
</head>
<body class="admin-dashboard">
    <div class="d-flex min-vh-100">
        <!-- Sidebar -->
        <div class="d-none d-md-flex flex-column" style="width: 250px; min-height: 100vh; background: #f8f9fa; border-right: 1px solid #dee2e6;">
            <?php include 'includes/admin_sidebar.php'; ?>
        </div>
        
        <!-- Mobile Sidebar Toggle -->
        <div class="d-md-none position-fixed" style="z-index: 1000; top: 10px; left: 10px;">
            <button class="btn btn-primary" id="mobileSidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        
        <!-- Main Content -->
        <div class="flex-grow-1" style="margin-left: 0; padding: 20px; width: calc(100% - 250px); overflow-x: hidden;">
            <!-- Top Navigation -->
            <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
                <div class="container-fluid">
                    <button class="btn btn-link text-white me-3 d-lg-none" type="button" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <span class="navbar-brand">Manage Polls</span>
                    <div class="d-flex align-items-center ms-auto">
                        <a href="new-poll.php" class="btn btn-light btn-sm me-2">
                            <i class="fas fa-plus me-1"></i> Create New Poll
                        </a>
                        <a href="index.php" class="btn btn-outline-light btn-sm">
                            <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </nav>

            <!-- Main Content -->
            <div class="container-fluid p-4 flex-grow-1">
                <div class="container">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['success']; 
                        unset($_SESSION['success']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <!-- Status Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="text-center">
                            <h5 class="card-title mb-3">Filter Polls</h5>
                            <div class="d-flex flex-wrap justify-content-center gap-2">
                                <?php
                                $statuses = [
                                    'active' => ['Active', 'check-circle', 'success'],
                                    'upcoming' => ['Upcoming', 'clock', 'info'],
                                    'ended' => ['Ended', 'flag-checkered', 'secondary'],
                                    'draft' => ['Drafts', 'file-alt', 'warning']
                                ];
                                
                                foreach ($statuses as $key => $data):
                                    list($label, $icon, $color) = $data;
                                    $is_active = $status === $key;
                                ?>
                                <a href="?status=<?= $key ?>" 
                                   class="btn btn-sm btn-outline-<?= $is_active ? $color . ' active' : 'secondary' ?>">
                                    <i class="fas fa-<?= $icon ?> me-1"></i> <?= $label ?>
                                    <?php if ($is_active): ?>
                                        <span class="badge bg-<?= $color ?> ms-1"><?= $total_polls ?></span>
                                    <?php endif; ?>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Polls List -->
                <?php if (empty($polls)): ?>
                    <div class="d-flex justify-content-center mt-5">
                        <div class="alert alert-info w-100 text-center">
                            <i class="fas fa-info-circle me-2"></i> No polls found.
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row justify-content-center">
                        <div class="col-12">
                            <div class="d-flex justify-content-start w-100">
                                <div style="width: 100%; max-width: none;">
                        <?php foreach ($polls as $poll): 
                            $status_class = $poll['status'];
                            $status_label = ucfirst($poll['status']);
                            $total_votes = (int)$poll['total_votes'];
                            
                            // Get poll results for the chart
                            $results = [];
                            $options = $db->prepare("
                                SELECT po.*, 
                                    (SELECT COUNT(*) FROM poll_votes pv WHERE pv.option_id = po.id) as vote_count
                                FROM poll_options po 
                                WHERE po.poll_id = ? 
                                ORDER BY po.id
                            ");
                            $options->execute([$poll['id']]);
                            $options = $options->fetchAll();
                            
                            $max_votes = 0;
                            foreach ($options as $option) {
                                $max_votes = max($max_votes, (int)$option['vote_count']);
                            }
                            
                            // Format dates
                            $start_date = $poll['start_date'] ? date('M j, Y', strtotime($poll['start_date'])) : 'Not started';
                            $end_date = $poll['end_date'] ? date('M j, Y', strtotime($poll['end_date'])) : 'No end date';
                        ?>
                        <div class="col-md-6 col-lg-4">
                        <div class="col">
                            <div class="card h-100 poll-card <?= $status_class ?>">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><?= htmlspecialchars($poll['question']) ?></h5>
                                    <div>
                                        <span class="badge bg-<?= 
                                            $status_class === 'active' ? 'success' : 
                                            ($status_class === 'upcoming' ? 'info' : 
                                            ($status_class === 'draft' ? 'warning' : 'secondary')) 
                                        ?> me-1">
                                            <?= $status_label ?>
                                        </span>
                                        <div class="btn-group">
                                            <a href="edit-poll.php?id=<?= $poll['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?delete=<?= $poll['id'] ?>" 
                                               class="btn btn-sm btn-outline-danger"
                                               onclick="return confirm('Are you sure you want to delete this poll? This action cannot be undone.')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <small class="text-muted">
                                                <i class="far fa-calendar-alt me-1"></i> 
                                                <?= $start_date ?> - <?= $end_date ?>
                                            </small>
                                            <small class="text-muted">
                                                <i class="fas fa-users me-1"></i> 
                                                <?= $total_votes ?> vote<?= $total_votes !== 1 ? 's' : '' ?>
                                            </small>
                                        </div>
                                        
                                        <?php if ($poll['status'] === 'active' || $poll['status'] === 'ended'): ?>
                                            <div class="progress mb-3" style="height: 5px;">
                                                <?php 
                                                $total_options = count($options);
                                                $total_votes_all = array_sum(array_column($options, 'vote_count'));
                                                $total_votes_all = $total_votes_all > 0 ? $total_votes_all : 1; // Avoid division by zero
                                                ?>
                                                <?php foreach ($options as $i => $option): 
                                                    $width = ($option['vote_count'] / $total_votes_all) * 100;
                                                    $colors = ['primary', 'success', 'info', 'warning', 'danger', 'secondary', 'dark'];
                                                    $color = $colors[$i % count($colors)];
                                                ?>
                                                    <div class="progress-bar bg-<?= $color ?>" 
                                                         role="progressbar" 
                                                         style="width: <?= $width ?>%" 
                                                         aria-valuenow="<?= $width ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100"
                                                         title="<?= htmlspecialchars($option['option_text']) ?>: <?= $option['vote_count'] ?> votes (<?= round($width, 1) ?>%)">
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="options">
                                            <?php foreach ($options as $i => $option): 
                                                $percentage = $total_votes > 0 ? round(($option['vote_count'] / $total_votes) * 100, 1) : 0;
                                            ?>
                                                <div class="mb-2">
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <span class="fw-medium"><?= htmlspecialchars($option['option_text']) ?></span>
                                                        <span class="text-muted"><?= $percentage ?>%</span>
                                                    </div>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar bg-<?= ['primary', 'success', 'info', 'warning', 'danger', 'secondary', 'dark'][$i % 7] ?>" 
                                                             role="progressbar" 
                                                             style="width: <?= $percentage ?>%" 
                                                             aria-valuenow="<?= $percentage ?>" 
                                                             aria-valuemin="0" 
                                                             aria-valuemax="100">
                                                            <?= $option['vote_count'] ?> vote<?= $option['vote_count'] != 1 ? 's' : '' ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                                        <small class="text-muted">
                                            Created: <?= date('M j, Y', strtotime($poll['created_at'])) ?>
                                        </small>
                                        <a href="view-poll.php?id=<?= $poll['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye me-1"></i> View
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Polls pagination" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?status=<?= $status ?>&page=<?= $page - 1 ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?status=<?= $status ?>&page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?status=<?= $status ?>&page=<?= $page + 1 ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                    
                <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar on mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.admin-dashboard').classList.toggle('sidebar-toggled');
        });
        
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html>
