<?php
require_once 'config/database.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user's active subscriptions with server and lesson details
$stmt = $conn->prepare("
    SELECT s.id as server_id,
           s.name as server_name,
           s.description as server_description,
           l.id as lesson_id,
           l.title as lesson_title,
           l.description as lesson_description,
           l.file_path,
           l.created_at,
           (SELECT COUNT(*) FROM reviews WHERE lesson_id = l.id) as review_count,
           (SELECT AVG(rating) FROM reviews WHERE lesson_id = l.id) as average_rating
    FROM subscriptions sub
    JOIN servers s ON sub.server_id = s.id
    LEFT JOIN lessons l ON s.id = l.server_id
    WHERE sub.user_id = ? AND sub.status = 'active'
    ORDER BY s.name ASC, l.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$lessons = $stmt->fetchAll();

// Group lessons by server
$servers = [];
foreach ($lessons as $lesson) {
    if (!isset($servers[$lesson['server_id']])) {
        $servers[$lesson['server_id']] = [
            'name' => $lesson['server_name'],
            'description' => $lesson['server_description'],
            'lessons' => []
        ];
    }
    if ($lesson['lesson_id']) {
        $servers[$lesson['server_id']]['lessons'][] = $lesson;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Lessons - Learning Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container mt-4">
        <h2>My Subscribed Lessons</h2>
        
        <?php if (empty($servers)): ?>
            <div class="alert alert-info">
                You haven't subscribed to any servers yet. 
                <a href="browse_servers.php" class="alert-link">Browse available servers</a>.
            </div>
        <?php else: ?>
            <?php foreach ($servers as $server_id => $server): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="mb-0"><?php echo htmlspecialchars($server['name']); ?></h3>
                        <p class="text-muted mb-0"><?php echo htmlspecialchars($server['description']); ?></p>
                    </div>
                    <div class="card-body">
                        <?php if (empty($server['lessons'])): ?>
                            <p class="text-muted">No lessons available yet.</p>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($server['lessons'] as $lesson): ?>
                                    <div class="col-md-6 mb-4">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <h5 class="card-title"><?php echo htmlspecialchars($lesson['lesson_title']); ?></h5>
                                                <p class="card-text"><?php echo htmlspecialchars($lesson['lesson_description']); ?></p>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <?php if ($lesson['average_rating']): ?>
                                                            <div class="text-warning">
                                                                <?php
                                                                $rating = round($lesson['average_rating']);
                                                                for ($i = 1; $i <= 5; $i++) {
                                                                    echo $i <= $rating ? '★' : '☆';
                                                                }
                                                                ?>
                                                            </div>
                                                            <small class="text-muted">
                                                                <?php echo $lesson['review_count']; ?> reviews
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <a href="download_lesson.php?id=<?php echo $lesson['lesson_id']; ?>" 
                                                           class="btn btn-primary">
                                                            Download
                                                        </a>
                                                        <button class="btn btn-success" 
                                                                onclick="showReviewModal(<?php echo $lesson['lesson_id']; ?>)">
                                                            Review
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Review Modal -->
    <div class="modal fade" id="reviewModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Submit Review</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="review-form" action="submit_review.php" method="POST">
                        <input type="hidden" id="lesson_id" name="lesson_id">
                        <div class="mb-3">
                            <label for="rating" class="form-label">Rating</label>
                            <select class="form-select" id="rating" name="rating" required>
                                <option value="">Select rating</option>
                                <option value="5">5 stars</option>
                                <option value="4">4 stars</option>
                                <option value="3">3 stars</option>
                                <option value="2">2 stars</option>
                                <option value="1">1 star</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="comment" class="form-label">Comment</label>
                            <textarea class="form-control" id="comment" name="comment" rows="3" required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="review-form" class="btn btn-primary">Submit Review</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const modal = new bootstrap.Modal(document.getElementById('reviewModal'));
        
        function showReviewModal(lessonId) {
            document.getElementById('lesson_id').value = lessonId;
            modal.show();
        }
    </script>
</body>
</html> 