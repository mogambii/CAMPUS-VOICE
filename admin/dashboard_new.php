<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/functions.php';
$db = getDB();

// Get user data from session
$user = [
    'id' => $_SESSION['user_id'],
    'name' => $_SESSION['name'] ?? 'User',
    'email' => $_SESSION['email'] ?? '',
];

// Get feedback statistics
$stats = [
    'total_feedback' => 0,
    'pending' => 0,
    'in_progress' => 0,
    'resolved' => 0,
    'active_polls' => 0,
    'pending_surveys' => 0,
    'duplicates_found' => 0
];

// Get feedback statistics
$feedback_stats = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress
    FROM feedback
")->fetch();

if ($feedback_stats) {
    $stats = array_merge($stats, $feedback_stats);
}

// Get recent feedback with AI analysis
$recent_feedback = $db->query("
    SELECT f.*, u.first_name, u.last_name
    FROM feedback f
    JOIN users u ON f.user_id = u.id
    ORDER BY f.created_at DESC
    LIMIT 5
")->fetchAll();

// Get active polls
$active_polls = $db->query("
    SELECT p.*, 
           (SELECT COUNT(*) FROM poll_responses WHERE poll_id = p.id) as response_count
    FROM polls p
    WHERE p.end_date > NOW()
    ORDER BY p.created_at DESC
    LIMIT 3
")->fetchAll();

// Get recent surveys
$recent_surveys = $db->query("
    SELECT s.*, 
           (SELECT COUNT(*) FROM survey_responses WHERE survey_id = s.id) as response_count
    FROM surveys s
    ORDER BY s.created_at DESC
    LIMIT 3
")->fetchAll();

// Social media metrics (placeholder data)
$social_metrics = [
    'twitter' => ['followers' => 0, 'mentions' => 0],
    'facebook' => ['followers' => 0, 'mentions' => 0],
    'instagram' => ['followers' => 0, 'mentions' => 0]
];

// Check for duplicate feedback (AI-powered)
$duplicate_alerts = [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Campus Voice</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/apexcharts@3.35.0/dist/apexcharts.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --success: #4bb543;
            --info: #4895ef;
            --warning: #f7b801;
            --danger: #f72585;
            --light: #f8f9fa;
            --dark: #212529;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fb;
        }
        .sidebar {
            min-height: 100vh;
            background: #fff;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        .nav-link {
            color: #4a5568;
            border-radius: 8px;
            margin: 4px 0;
            font-weight: 500;
            padding: 10px 15px;
            transition: all 0.3s;
        }
        .nav-link:hover, .nav-link.active {
            background: var(--primary);
            color: white !important;
        }
        .nav-link i {
            width: 24px;
            text-align: center;
            margin-right: 8px;
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
            margin-bottom: 1.5rem;
            border-left: 4px solid transparent;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .stat-card {
            padding: 20px;
            border-radius: 12px;
            background: white;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        .stat-card i {
            font-size: 2rem;
            margin-bottom: 15px;
            padding: 15px;
            border-radius: 10px;
            color: white;
        }
        .stat-card .icon-primary { background: rgba(67, 97, 238, 0.2); color: var(--primary); }
        .stat-card .icon-success { background: rgba(75, 181, 67, 0.2); color: var(--success); }
        .stat-card .icon-warning { background: rgba(247, 184, 1, 0.2); color: var(--warning); }
        .stat-card .icon-danger { background: rgba(247, 37, 133, 0.2); color: var(--danger); }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            font-size: 0.7rem;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        .progress {
            height: 6px;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar p-0">
                <div class="position-sticky">
                    <div class="p-3">
                        <h4 class="text-primary mb-4">
                            <i class="fas fa-comment-dots me-2"></i>Campus Voice
                        </h4>
                        <ul class="nav flex-column">
                            <li class="nav-item">
                                <a class="nav-link active" href="dashboard.php">
                                    <i class="fas fa-tachometer-alt"></i> Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="feedback.php">
                                    <i class="fas fa-comment-dots"></i> Feedback
                                    <?php if(count($duplicate_alerts) > 0): ?>
                                        <span class="badge bg-danger notification-badge"><?php echo count($duplicate_alerts); ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="polls.php">
                                    <i class="fas fa-poll"></i> Polls
                                    <?php if(count($active_polls) > 0): ?>
                                        <span class="badge bg-success notification-badge"><?php echo count($active_polls); ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="surveys.php">
                                    <i class="fas fa-clipboard-list"></i> Surveys
                                    <?php if(count($recent_surveys) > 0): ?>
                                        <span class="badge bg-info notification-badge"><?php echo count($recent_surveys); ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="analytics.php">
                                    <i class="fas fa-chart-line"></i> Analytics
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="social.php">
                                    <i class="fas fa-share-alt"></i> Social Media
                                </a>
                            </li>
                            <li class="nav-item mt-4">
                                <a class="nav-link" href="settings.php">
                                    <i class="fas fa-cog"></i> Settings
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <!-- Top Navigation -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['name']); ?>" class="user-avatar me-2">
                                <?php echo htmlspecialchars(explode(' ', $user['name'])[0]); ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton">
                                <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                                <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stat-card">
                            <div class="d-flex align-items-center">
                                <div class="icon-primary me-3">
                                    <i class="fas fa-comment-dots"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-1">Total Feedback</h6>
                                    <h4 class="mb-0"><?php echo $stats['total']; ?></h4>
                                </div>
                            </div>
                            <div class="mt-3">
                                <a href="feedback.php" class="text-primary text-decoration-none small">
                                    View all <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stat-card">
                            <div class="d-flex align-items-center">
                                <div class="icon-warning me-3">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-1">Pending</h6>
                                    <h4 class="mb-0"><?php echo $stats['pending']; ?></h4>
                                </div>
                            </div>
                            <div class="mt-3">
                                <a href="feedback.php?status=pending" class="text-warning text-decoration-none small">
                                    View pending <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stat-card">
                            <div class="d-flex align-items-center">
                                <div class="icon-info me-3">
                                    <i class="fas fa-spinner"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-1">In Progress</h6>
                                    <h4 class="mb-0"><?php echo $stats['in_progress']; ?></h4>
                                </div>
                            </div>
                            <div class="mt-3">
                                <a href="feedback.php?status=in_progress" class="text-info text-decoration-none small">
                                    View in progress <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stat-card">
                            <div class="d-flex align-items-center">
                                <div class="icon-success me-3">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-1">Resolved</h6>
                                    <h4 class="mb-0"><?php echo $stats['resolved']; ?></h4>
                                </div>
                            </div>
                            <div class="mt-3">
                                <a href="feedback.php?status=resolved" class="text-success text-decoration-none small">
                                    View resolved <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content Row -->
                <div class="row">
                    <!-- Left Column -->
                    <div class="col-lg-8">
                        <!-- Feedback Overview Chart -->
                        <div class="card mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Feedback Overview</h5>
                            </div>
                            <div class="card-body">
                                <div id="feedbackChart" style="height: 300px;"></div>
                            </div>
                        </div>

                        <!-- Recent Feedback -->
                        <div class="card mb-4">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Recent Feedback</h5>
                                <a href="feedback.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($recent_feedback)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-comment-slash fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No feedback submitted yet.</p>
                                        <a href="submit-feedback.php" class="btn btn-primary">Submit Feedback</a>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($recent_feedback as $feedback): 
                                            $isFlagged = $feedback['is_flagged'] ?? false;
                                        ?>
                                            <div class="list-group-item">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1">
                                                        <?php echo htmlspecialchars($feedback['title']); ?>
                                                        <?php if ($isFlagged): ?>
                                                            <span class="badge bg-danger ms-2">Flagged</span>
                                                        <?php endif; ?>
                                                    </h6>
                                                    <div>
                                                        <button type="button" class="btn btn-sm btn-outline-<?php echo $isFlagged ? 'danger' : 'warning'; ?> flag-feedback" 
                                                                data-id="<?php echo $feedback['id']; ?>"
                                                                data-flagged="<?php echo $isFlagged ? '1' : '0'; ?>"
                                                                title="<?php echo $isFlagged ? 'Already Flagged' : 'Flag as Inappropriate'; ?>">
                                                            <i class="fas fa-flag"></i> <?php echo $isFlagged ? 'Flagged' : 'Flag'; ?>
                                                        </button>
                                                    </div>
                                                </div>
                                                <p class="mb-1 text-muted">
                                                    <?php echo htmlspecialchars(substr($feedback['description'] ?? 'No description', 0, 100)); ?>...
                                                </p>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-muted">
                                                        <i class="fas fa-user me-1"></i>
                                                        <?php echo htmlspecialchars($feedback['first_name'] . ' ' . $feedback['last_name']); ?>
                                                        <span class="ms-2">
                                                            <i class="far fa-calendar-alt me-1"></i>
                                                            <?php echo date('M d, Y', strtotime($feedback['created_at'])); ?>
                                                        </span>
                                                    </small>
                                                    <span class="badge bg-<?php 
                                                        echo $feedback['status'] === 'resolved' ? 'success' : 
                                                            ($feedback['status'] === 'in_progress' ? 'warning' : 'secondary'); 
                                                    ?> text-capitalize">
                                                        <?php echo str_replace('_', ' ', $feedback['status']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="col-lg-4">
                        <!-- Quick Actions -->
                        <div class="card mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="submit-feedback.php" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>Submit Feedback
                                    </a>
                                    <a href="create-poll.php" class="btn btn-outline-primary">
                                        <i class="fas fa-plus me-2"></i>Create Poll
                                    </a>
                                    <a href="create-survey.php" class="btn btn-outline-primary">
                                        <i class="fas fa-plus me-2"></i>Create Survey
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Active Polls -->
                        <div class="card mb-4">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Active Polls</h5>
                                <a href="polls.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($active_polls)): ?>
                                    <div class="text-center p-4">
                                        <i class="fas fa-poll fa-2x text-muted mb-3"></i>
                                        <p class="text-muted mb-0">No active polls at the moment.</p>
                                        <a href="create-poll.php" class="btn btn-sm btn-outline-primary mt-2">Create Poll</a>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($active_polls as $poll): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($poll['question']); ?></h6>
                                                    <small class="text-muted">
                                                        <?php echo $poll['response_count']; ?> votes
                                                    </small>
                                                </div>
                                                <div class="progress mt-2" style="height: 6px;">
                                                    <div class="progress-bar bg-primary" role="progressbar" style="width: 75%;" 
                                                         aria-valuenow="75" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                                <div class="d-flex justify-content-between mt-1">
                                                    <small class="text-muted">Ends <?php echo date('M d', strtotime($poll['end_date'])); ?></small>
                                                    <a href="view-poll.php?id=<?php echo $poll['id']; ?>" class="btn btn-sm btn-outline-primary btn-sm">Vote</a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Social Media Integration -->
                        <div class="card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Social Media</h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="p-2 bg-light rounded mb-2">
                                            <i class="fab fa-twitter text-primary fa-2x mb-2"></i>
                                            <h6 class="mb-0"><?php echo number_format($social_metrics['twitter']['followers']); ?></h6>
                                            <small class="text-muted">Followers</small>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="p-2 bg-light rounded mb-2">
                                            <i class="fab fa-facebook text-primary fa-2x mb-2"></i>
                                            <h6 class="mb-0"><?php echo number_format($social_metrics['facebook']['followers']); ?></h6>
                                            <small class="text-muted">Likes</small>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="p-2 bg-light rounded mb-2">
                                            <i class="fab fa-instagram text-danger fa-2x mb-2"></i>
                                            <h6 class="mb-0"><?php echo number_format($social_metrics['instagram']['followers']); ?></h6>
                                            <small class="text-muted">Followers</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-center mt-3">
                                    <button class="btn btn-sm btn-outline-primary me-2">
                                        <i class="fas fa-sync-alt me-1"></i> Refresh
                                    </button>
                                    <button class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-share-alt me-1"></i> Share
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Handle flag feedback action
    document.addEventListener('DOMContentLoaded', function() {
        const flagButtons = document.querySelectorAll('.flag-feedback');
        
        flagButtons.forEach(button => {
            button.addEventListener('click', function() {
                const feedbackId = this.getAttribute('data-id');
                const isFlagged = this.getAttribute('data-flagged') === '1';
                
                if (isFlagged) {
                    alert('This feedback has already been flagged as inappropriate.');
                    return;
                }
                
                const reason = prompt('Please specify the reason for flagging this feedback:', 'Inappropriate content');
                
                if (reason !== null && reason.trim() !== '') {
                    // Here you would typically make an AJAX call to flag the feedback
                    // For now, we'll just update the UI
                    this.innerHTML = '<i class="fas fa-flag"></i> Flagged';
                    this.classList.remove('btn-outline-warning');
                    this.classList.add('btn-outline-danger');
                    this.setAttribute('data-flagged', '1');
                    this.title = 'Already Flagged';
                    
                    // Add a badge next to the title if it doesn't exist
                    const title = this.closest('.list-group-item').querySelector('h6');
                    if (!title.querySelector('.badge')) {
                        const badge = document.createElement('span');
                        badge.className = 'badge bg-danger ms-2';
                        badge.textContent = 'Flagged';
                        title.insertBefore(badge, title.firstChild.nextSibling);
                    }
                    
                    // Show success message
                    alert('Feedback has been flagged for review. Thank you for your input.');
                    
                    // In a real implementation, you would make an AJAX call here:
                    /*
                    fetch('flag-feedback.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            feedback_id: feedbackId,
                            reason: reason
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update UI on success
                        } else {
                            alert('Failed to flag feedback: ' + (data.message || 'Unknown error'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while flagging the feedback.');
                    });
                    */
                }
            });
        });
    });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.35.0/dist/apexcharts.min.js"></script>
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Feedback Overview Chart
        var options = {
            series: [{
                name: 'Feedback',
                data: [31, 40, 28, 51, 42, 82, 56, 71, 69, 45, 32, 55]
            }],
            chart: {
                height: 300,
                type: 'area',
                toolbar: {
                    show: false
                },
                zoom: {
                    enabled: false
                }
            },
            dataLabels: {
                enabled: false
            },
            stroke: {
                curve: 'smooth',
                width: 2
            },
            colors: ['#4361ee'],
            xaxis: {
                categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                labels: {
                    style: {
                        colors: '#6c757d',
                        fontSize: '12px'
                    }
                }
            },
            yaxis: {
                labels: {
                    style: {
                        colors: '#6c757d',
                        fontSize: '12px'
                    }
                }
            },
            tooltip: {
                y: {
                    formatter: function (val) {
                        return val + " feedback"
                    }
                }
            },
            grid: {
                borderColor: '#f1f1f1',
                strokeDashArray: 4,
                yaxis: {
                    lines: {
                        show: true
                    }
                }
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.7,
                    opacityTo: 0.3,
                    stops: [0, 90, 100]
                }
            }
        };

        var chart = new ApexCharts(document.querySelector("#feedbackChart"), options);
        chart.render();
    </script>
</body>
</html>
