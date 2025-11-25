<?php
define('IN_CAMPUS_VOICE', true);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Get database connection
$db = getDB();

// Ensure user is admin
requireAdmin();

// Get filter parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // Default to start of current month
$end_date = $_GET['end_date'] ?? date('Y-m-t'); // Default to end of current month
$status = $_GET['status'] ?? '';
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

// Build the base query
$query = "SELECT f.*, c.name as category_name, 
          CONCAT(u.first_name, ' ', u.last_name) as user_name, u.email
          FROM feedback f
          LEFT JOIN categories c ON f.category_id = c.id
          LEFT JOIN users u ON f.user_id = u.id
          WHERE f.created_at BETWEEN :start_date AND DATE_ADD(:end_date, INTERVAL 1 DAY)";

$params = [
    ':start_date' => $start_date,
    ':end_date' => $end_date
];

// Add status filter if provided
if (!empty($status)) {
    $query .= " AND f.status = :status";
    $params[':status'] = $status;
}

// Add category filter if provided
if ($category_id > 0) {
    $query .= " AND f.category_id = :category_id";
    $params[':category_id'] = $category_id;
}

// Add sorting
$query .= " ORDER BY f.created_at DESC";

// Prepare and execute the query
$stmt = $db->prepare($query);
$stmt->execute($params);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter dropdown
$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Handle export to PDF
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    // Generate PDF (you'll need to implement this part with a PDF library like TCPDF or mPDF)
    // For now, we'll just show the print view
    $print_view = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Reports - Campus Voice</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Base Typography */
        body {
            font-family: 'Garamond', 'Georgia', 'Times New Roman', serif;
            color: #333;
            background-color: #f8f5f0; /* Warm cream background */
            line-height: 1.6;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Garamond', 'Georgia', 'Times New Roman', serif;
            font-weight: 600;
            color: #2c3e50; /* Charcoal for headings */
            letter-spacing: 0.5px;
        }

        /* Card Styling */
        .card {
            border: 1px solid #d4c9b8; /* Warm tan border */
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            background-color: #fff;
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .card-header {
            background-color: #f0e9dd; /* Light tan header */
            border-bottom: 1px solid #d4c9b8;
            padding: 1.25rem 1.5rem;
        }

        .card-header h4 {
            margin: 0;
            color: #5d4b36; /* Darker brown for card titles */
            font-size: 1.5rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Buttons */
        .btn {
            border-radius: 3px;
            font-family: 'Garamond', 'Georgia', serif;
            font-weight: 500;
            letter-spacing: 0.5px;
            padding: 0.5rem 1.25rem;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }

        .btn-primary {
            background-color: #8b7355; /* Warm brown */
            border-color: #7a6348;
            color: #fff;
        }

        .btn-primary:hover {
            background-color: #7a6348;
            border-color: #69553d;
        }

        .btn-outline-secondary {
            color: #8b7355;
            border-color: #d4c9b8;
            background-color: transparent;
        }

        .btn-outline-secondary:hover {
            background-color: #f0e9dd;
            border-color: #8b7355;
        }

        /* Tables */
        .table {
            width: 100%;
            margin-bottom: 1.5rem;
            border-collapse: collapse;
        }

        .table thead th {
            background-color: #f0e9dd;
            color: #5d4b36;
            font-weight: 600;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #d4c9b8;
            text-align: left;
        }

        .table tbody td {
            padding: 1rem;
            border-bottom: 1px solid #e9e1d4;
            vertical-align: middle;
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        .table tbody tr:hover {
            background-color: #f9f7f2;
        }

        /* Badges */
        .badge {
            font-weight: 500;
            padding: 0.35em 0.65em;
            border-radius: 3px;
            font-size: 0.85em;
            letter-spacing: 0.5px;
        }

        .bg-primary {
            background-color: #8b7355 !important;
        }

        .bg-success {
            background-color: #7a8b55 !important; /* Muted green */
        }

        .bg-warning {
            background-color: #b38b58 !important; /* Muted orange */
        }

        .bg-secondary {
            background-color: #a8a39d !important; /* Muted gray */
        }

        /* Form Controls */
        .form-control, .form-select {
            border: 1px solid #d4c9b8;
            border-radius: 3px;
            padding: 0.5rem 0.75rem;
            font-family: 'Garamond', 'Georgia', serif;
            background-color: #fff;
        }

        .form-control:focus, .form-select:focus {
            border-color: #8b7355;
            box-shadow: 0 0 0 0.2rem rgba(139, 115, 85, 0.1);
        }

        /* Print Styles */
        @media print {
            .no-print {
                display: none !important;
            }
            .print-section {
                display: block !important;
            }
            body {
                padding: 20px;
                font-size: 12px;
                background: #fff;
                color: #000;
            }
            .table {
                font-size: 12px;
                border: 1px solid #ddd;
            }
            .card {
                border: 1px solid #ddd;
                box-shadow: none;
            }
        }

        .print-section {
            display: none;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .card-body {
                padding: 1rem;
            }
            
            .btn {
                padding: 0.4rem 0.8rem;
                font-size: 0.9rem;
            }
            
            .table thead {
                display: none;
            }
            
            .table, .table tbody, .table tr, .table td {
                display: block;
                width: 100%;
            }
            
            .table tr {
                margin-bottom: 1rem;
                border: 1px solid #d4c9b8;
                border-radius: 4px;
                padding: 0.5rem;
            }
            
            .table td {
                text-align: right;
                padding-left: 50%;
                position: relative;
                border-bottom: 1px solid #f0e9dd;
            }
            
            .table td::before {
                content: attr(data-label);
                position: absolute;
                left: 1rem;
                width: 45%;
                text-align: left;
                font-weight: 600;
                color: #5d4b36;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="mb-4">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Feedback Reports</h4>
                        <div class="no-print">
                            <a href="?export=pdf&<?php echo http_build_query($_GET); ?>" class="btn btn-primary me-2">
                                <i class="fas fa-file-pdf me-1"></i> Export to PDF
                            </a>
                            <button onclick="window.print()" class="btn btn-outline-secondary">
                                <i class="fas fa-print me-1"></i> Print
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="get" class="mb-4 no-print">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">End Date</label>
                                    <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="">All Statuses</option>
                                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="in_progress" <?php echo $status === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                        <option value="resolved" <?php echo $status === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                        <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Category</label>
                                    <select name="category_id" class="form-select">
                                        <option value="0">All Categories</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>" <?php echo $category_id == $category['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-1 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-filter me-1"></i> Filter
                                    </button>
                                </div>
                            </div>
                        </form>

                        <!-- Print Header -->
                        <div class="print-section text-center mb-4">
                            <h2>Feedback Report</h2>
                            <p class="mb-1">Date Range: <?php echo date('M j, Y', strtotime($start_date)); ?> to <?php echo date('M j, Y', strtotime($end_date)); ?></p>
                            <?php if (!empty($status)): ?>
                                <p class="mb-1">Status: <?php echo ucfirst(str_replace('_', ' ', $status)); ?></p>
                            <?php endif; ?>
                            <?php if ($category_id > 0): ?>
                                <?php 
                                $cat_name = '';
                                foreach ($categories as $cat) {
                                    if ($cat['id'] == $category_id) {
                                        $cat_name = $cat['name'];
                                        break;
                                    }
                                }
                                ?>
                                <p class="mb-1">Category: <?php echo htmlspecialchars($cat_name); ?></p>
                            <?php endif; ?>
                            <p>Generated on: <?php echo date('M j, Y h:i A'); ?></p>
                            <hr>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Title</th>
                                        <th>Category</th>
                                        <th>Submitted By</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($reports)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4">
                                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                                <p class="mb-0">No feedback found matching your criteria.</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($reports as $report): ?>
                                            <tr>
                                                <td data-label="ID">#<?php echo $report['id']; ?></td>
                                                <td data-label="Title"><?php echo htmlspecialchars($report['title']); ?></td>
                                                <td data-label="Category"><?php echo htmlspecialchars($report['category_name'] ?? 'Uncategorized'); ?></td>
                                                <td data-label="Submitted By">
                                                    <?php 
                                                    if (isset($report['anonymous']) && $report['anonymous'] == 1) {
                                                        echo '<span class="badge bg-secondary">Anonymous</span>';
                                                    } else {
                                                        echo htmlspecialchars($report['user_name'] ?? 'Unknown');
                                                    }
                                                    ?>
                                                </td>
                                                <td data-label="Date"><?php echo date('M j, Y', strtotime($report['created_at'])); ?></td>
                                                <td data-label="Status"><span class="badge <?php 
                                                    switch(strtolower($report['status'])) {
                                                        case 'pending':
                                                            echo 'warning';
                                                            break;
                                                        case 'in_progress':
                                                            echo 'info';
                                                            break;
                                                        case 'resolved':
                                                            echo 'success';
                                                            break;
                                                        case 'rejected':
                                                            echo 'danger';
                                                            break;
                                                        default:
                                                            echo 'secondary';
                                                    }
                                                ?>"><?php echo ucfirst(str_replace('_', ' ', $report['status'])); ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if (!empty($reports)): ?>
                            <div class="mt-4 text-muted print-section">
                                <p>Total Records: <?php echo count($reports); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-submit form when filters change
        document.addEventListener('DOMContentLoaded', function() {
            const filterForm = document.querySelector('form[method="get"]');
            const filterInputs = filterForm.querySelectorAll('select, input[type="date"]');
            
            filterInputs.forEach(input => {
                input.addEventListener('change', function() {
                    filterForm.submit();
                });
            });
        });
    </script>
</body>
</html>
