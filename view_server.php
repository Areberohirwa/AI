<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if server ID is provided
if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$server_id = $_GET['id'];

// Get server details
$stmt = $conn->prepare("
    SELECT s.*, u.username as provider_name 
    FROM servers s 
    JOIN users u ON s.provider_id = u.id 
    WHERE s.id = ?
");
$stmt->execute([$server_id]);
$server = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$server) {
    header("Location: dashboard.php");
    exit();
}

// Check if user is subscribed to this server
$stmt = $conn->prepare("SELECT * FROM subscriptions WHERE user_id = ? AND server_id = ? AND status = 'active'");
$stmt->execute([$_SESSION['user_id'], $server_id]);
$subscription = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$subscription && $_SESSION['role'] !== 'provider') {
    header("Location: subscribe.php?id=" . $server_id);
    exit();
}

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_review') {
    $lesson_id = $_POST['lesson_id'];
    $rating = intval($_POST['rating']);
    $comment = trim($_POST['comment']);
    
    $errors = [];
    if ($rating < 1 || $rating > 5) $errors[] = "Rating must be between 1 and 5";
    if (empty($comment)) $errors[] = "Comment is required";
    
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO reviews (user_id, lesson_id, rating, comment) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$_SESSION['user_id'], $lesson_id, $rating, $comment])) {
            $_SESSION['success'] = "Review submitted successfully!";
        } else {
            $errors[] = "Failed to submit review. Please try again.";
        }
    }
}

// Get all lessons for this server
$stmt = $conn->prepare("
    SELECT l.*, 
           (SELECT COUNT(*) FROM reviews WHERE lesson_id = l.id) as review_count,
           (SELECT AVG(rating) FROM reviews WHERE lesson_id = l.id) as average_rating
    FROM lessons l 
    WHERE l.server_id = ? 
    ORDER BY l.created_at DESC
");
$stmt->execute([$server_id]);
$lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($server['name']); ?> - Learning Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">Learning Platform</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <h2><?php echo htmlspecialchars($server['name']); ?></h2>
                <p class="text-muted">By <?php echo htmlspecialchars($server['provider_name']); ?></p>
                <p><?php echo htmlspecialchars($server['description']); ?></p>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php 
                echo htmlspecialchars($_SESSION['success']);
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <div class="row mt-4">
            <?php foreach ($lessons as $lesson): ?>
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($lesson['title']); ?></h5>
                            <p class="card-text"><?php echo htmlspecialchars($lesson['description']); ?></p>
                            <p class="card-text">
                                <small class="text-muted">
                                    Price: €<?php echo number_format($lesson['price'], 2); ?> | 
                                    Reviews: <?php echo $lesson['review_count']; ?> | 
                                    Rating: <?php echo number_format($lesson['average_rating'], 1); ?>/5
                                </small>
                            </p>
                            <?php if ($lesson['file_path']): ?>
                                <a href="<?php echo htmlspecialchars($lesson['file_path']); ?>" class="btn btn-primary" target="_blank">Download Lesson</a>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer">
                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#reviewModal<?php echo $lesson['id']; ?>">
                                Leave a Review
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Review Modal -->
                <div class="modal fade" id="reviewModal<?php echo $lesson['id']; ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Review <?php echo htmlspecialchars($lesson['title']); ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="submit_review">
                                    <input type="hidden" name="lesson_id" value="<?php echo $lesson['id']; ?>">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Rating</label>
                                        <div class="rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <input type="radio" name="rating" value="<?php echo $i; ?>" id="star<?php echo $i; ?>" required>
                                                <label for="star<?php echo $i; ?>">☆</label>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="comment" class="form-label">Comment</label>
                                        <textarea class="form-control" id="comment" name="comment" rows="3" required></textarea>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">Submit Review</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
        }
        .rating input {
            display: none;
        }
        .rating label {
            font-size: 30px;
            color: #ddd;
            cursor: pointer;
        }
        .rating input:checked ~ label,
        .rating label:hover,
        .rating label:hover ~ label {
            color: #ffd700;
        }
    </style>
</body>
</html> 