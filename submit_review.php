<?php
require_once 'config/database.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lesson_id = $_POST['lesson_id'];
    $rating = $_POST['rating'];
    $comment = $_POST['comment'];
    
    // Validate input
    if (!is_numeric($lesson_id) || !is_numeric($rating) || $rating < 1 || $rating > 5) {
        $_SESSION['error'] = "Invalid input data.";
        header("Location: my_lessons.php");
        exit();
    }
    
    // Check if user has access to this lesson
    $stmt = $conn->prepare("
        SELECT 1 FROM subscriptions sub
        JOIN lessons l ON sub.server_id = l.server_id
        WHERE sub.user_id = ? AND l.id = ? AND sub.status = 'active'
    ");
    $stmt->execute([$_SESSION['user_id'], $lesson_id]);
    
    if (!$stmt->fetch()) {
        $_SESSION['error'] = "You don't have access to this lesson.";
        header("Location: my_lessons.php");
        exit();
    }
    
    // Check if user has already reviewed this lesson
    $stmt = $conn->prepare("
        SELECT id FROM reviews 
        WHERE user_id = ? AND lesson_id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $lesson_id]);
    
    if ($stmt->fetch()) {
        $_SESSION['error'] = "You have already reviewed this lesson.";
        header("Location: my_lessons.php");
        exit();
    }
    
    // Insert the review
    $stmt = $conn->prepare("
        INSERT INTO reviews (user_id, lesson_id, rating, comment, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    
    try {
        $stmt->execute([$_SESSION['user_id'], $lesson_id, $rating, $comment]);
        $_SESSION['success'] = "Your review has been submitted successfully.";
    } catch (PDOException $e) {
        $_SESSION['error'] = "An error occurred while submitting your review.";
    }
}

header("Location: my_lessons.php");
exit();
?> 