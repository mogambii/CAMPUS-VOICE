<?php
require_once __DIR__ . '/includes/SocialFeed.php';

// Get search query from URL or use default
$query = isset($_GET['q']) ? trim($_GET['q']) : 'Strathmore University';

// Create new SocialFeed instance
$socialFeed = new SocialFeed();
$posts = $socialFeed->getFeed($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Social Media Feed - Campus Voice</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            font-family: Georgia, 'Times New Roman', Times, serif;
            background-color: var(--light-color);
            color: var(--dark-color);
        }
        .post {
            margin-bottom: 2rem;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            border: 1px solid #e0e0e0;
            transition: box-shadow 0.2s ease;
        }
        .post:hover {
            box-shadow: var(--shadow-md);
        }
        .post-header {
            padding: 1rem;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
        }
        .post-avatar {
            width: 40px;
            height: 40px;
            border-radius: 6px;
            margin-right: 10px;
            object-fit: cover;
        }
        .post-avatar i {
            font-size: 20px;
            line-height: 40px;
            text-align: center;
            width: 100%;
            color: #fff;
        }
        .post-avatar.twitter { background-color: var(--primary-color); }
        .post-avatar.instagram { background-color: var(--secondary-color); }
        .post-content {
            padding: 1rem;
        }
        .post-text {
            margin-bottom: 1rem;
            word-break: break-word;
            font-size: 1rem;
            line-height: 1.6;
        }
        .post-media {
            margin-bottom: 1rem;
            border-radius: 6px;
            overflow: hidden;
        }
        .post-media img, .post-media video {
            width: 100%;
            height: auto;
            display: block;
        }
        .post-footer {
            padding: 0.75rem 1rem;
            border-top: 1px solid #e0e0e0;
            font-size: 0.9rem;
            color: var(--dark-color);
        }
        .post-platform {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--secondary-color);
        }
        .loading {
            text-align: center;
            padding: 2rem;
            color: var(--dark-color);
        }
        .hashtag-input {
            max-width: 500px;
            margin: 2rem auto;
        }
        .hashtag-input .input-group-text {
            background: var(--light-color);
            color: var(--dark-color);
            border-color: #ccc;
        }
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: var(--light-color);
        }
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
            color: var(--light-color);
        }
        .spinner-border.text-primary {
            color: var(--primary-color) !important;
        }
        .text-primary {
            color: var(--primary-color) !important;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <h1 class="text-center mb-4">Social Media Feed</h1>
        
        <div class="search-container mb-4">
            <form method="get" class="d-flex">
                <input type="text" name="q" class="form-control me-2" value="<?php echo htmlspecialchars($query); ?>" placeholder="Search for posts...">
                <button type="submit" class="btn btn-primary">Search</button>
            </form>
        </div>
        
        <div id="feedContainer" class="row">
            <?php if (empty($posts)): ?>
                <div class="col-12">
                    <div class="alert alert-info">No posts found. Try a different search term.</div>
                </div>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex align-items-center">
                                <div class="me-2">
                                    <?php if ($post['platform'] === 'twitter'): ?>
                                        <i class="fab fa-twitter text-primary"></i>
                                    <?php else: ?>
                                        <i class="fab fa-reddit text-danger"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($post['author']['name']); ?></strong>
                                    <div class="text-muted small">
                                        <?php echo ucfirst($post['platform']); ?> · 
                                        <?php echo date('M j, Y g:i A', strtotime($post['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <p class="card-text"><?php echo nl2br(htmlspecialchars($post['text'])); ?></p>
                                <?php if (!empty($post['media'])): ?>
                                    <div class="mt-2">
                                        <?php foreach ($post['media'] as $media): ?>
                                            <?php if ($media['type'] === 'image'): ?>
                                                <img src="<?php echo htmlspecialchars($media['url']); ?>" class="img-fluid rounded mb-2" alt="Post media">
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer bg-transparent d-flex justify-content-between">
                                <div>
                                    <?php if ($post['metrics']['likes'] > 0): ?>
                                        <span class="me-3"><i class="far fa-heart text-danger"></i> <?php echo number_format($post['metrics']['likes']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($post['metrics']['comments'] > 0): ?>
                                        <span class="me-3"><i class="far fa-comment"></i> <?php echo number_format($post['metrics']['comments']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($post['metrics']['shares'] > 0): ?>
                                        <span><i class="fas fa-retweet text-success"></i> <?php echo number_format($post['metrics']['shares']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <a href="<?php echo htmlspecialchars($post['url']); ?>" target="_blank" class="text-decoration-none">
                                    View on <?php echo ucfirst($post['platform']); ?> <i class="fas fa-external-link-alt"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-refresh the page every 10 minutes
            setInterval(() => {
                window.location.reload();
            }, 600000);
        });
    </script>
</body>
</html>
