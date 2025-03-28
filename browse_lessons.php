<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user's active subscriptions
$stmt = $conn->prepare("SELECT server_id FROM subscriptions WHERE user_id = ? AND status = 'active'");
$stmt->execute([$_SESSION['user_id']]);
$user_subscriptions = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get all lessons with server and provider information
$stmt = $conn->prepare("
    SELECT l.*, s.name as server_name, s.id as server_id, u.username as provider_name,
           (SELECT COUNT(*) FROM reviews WHERE lesson_id = l.id) as review_count,
           (SELECT AVG(rating) FROM reviews WHERE lesson_id = l.id) as average_rating,
           CASE WHEN s.id IN (" . implode(',', array_fill(0, count($user_subscriptions), '?')) . ") THEN 1 ELSE 0 END as is_subscribed
    FROM lessons l
    JOIN servers s ON l.server_id = s.id
    JOIN users u ON s.provider_id = u.id
    ORDER BY is_subscribed DESC, l.created_at DESC
");

// Prepare the parameters array with server IDs
$params = array_merge($user_subscriptions, $user_subscriptions);
$stmt->execute($params);
$lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Lessons - Learning Platform</title>
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
        <h2>Available Lessons</h2>
        
        <div class="row mt-4">
            <?php foreach ($lessons as $lesson): ?>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 <?php echo $lesson['is_subscribed'] ? 'border-success' : ''; ?>">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($lesson['title']); ?></h5>
                            <p class="card-text"><?php echo htmlspecialchars($lesson['description']); ?></p>
                            <ul class="list-unstyled">
                                <li><strong>Server:</strong> <?php echo htmlspecialchars($lesson['server_name']); ?></li>
                                <li><strong>Provider:</strong> <?php echo htmlspecialchars($lesson['provider_name']); ?></li>
                                <li><strong>Price:</strong> €<?php echo number_format($lesson['price'], 2); ?></li>
                                <li><strong>Reviews:</strong> <?php echo $lesson['review_count']; ?></li>
                                <li><strong>Rating:</strong> <?php echo number_format($lesson['average_rating'], 1); ?>/5</li>
                            </ul>
                        </div>
                        <div class="card-footer">
                            <?php if ($lesson['is_subscribed']): ?>
                                <a href="view_server.php?id=<?php echo $lesson['server_id']; ?>" class="btn btn-success w-100">View Lesson</a>
                            <?php else: ?>
                                <a href="subscribe.php?id=<?php echo $lesson['server_id']; ?>" class="btn btn-primary w-100">Subscribe to Access</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 