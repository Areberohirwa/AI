<?php
require_once 'config/database.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid lesson ID.";
    header("Location: my_lessons.php");
    exit();
}

$lesson_id = $_GET['id'];

// Check if user has access to this lesson
$stmt = $conn->prepare("
    SELECT l.file_path, l.title
    FROM lessons l
    JOIN subscriptions sub ON l.server_id = sub.server_id
    WHERE l.id = ? AND sub.user_id = ? AND sub.status = 'active'
");
$stmt->execute([$lesson_id, $_SESSION['user_id']]);
$lesson = $stmt->fetch();

if (!$lesson) {
    $_SESSION['error'] = "You don't have access to this lesson.";
    header("Location: my_lessons.php");
    exit();
}

$file_path = $lesson['file_path'];
$file_name = $lesson['title'] . '.pdf'; // Assuming PDF files

// Check if file exists
if (!file_exists($file_path)) {
    $_SESSION['error'] = "The lesson file is not available.";
    header("Location: my_lessons.php");
    exit();
}

// Set headers for file download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $file_name . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Output file
readfile($file_path);
exit();
?> 