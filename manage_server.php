<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is an instructor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'provider') {
    header("Location: login.php");
    exit();
}

// Check if server ID is provided
if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$server_id = $_GET['id'];

// Verify that the user owns this server
$stmt = $conn->prepare("SELECT * FROM servers WHERE id = ? AND provider_id = ?");
$stmt->execute([$server_id, $_SESSION['user_id']]);
$server = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$server) {
    header("Location: dashboard.php");
    exit();
}

// Handle lesson upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_lesson') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    
    $errors = [];
    if (empty($title)) $errors[] = "Lesson title is required";
    if (empty($description)) $errors[] = "Description is required";
    if ($price < 0) $errors[] = "Price cannot be negative";
    
    if (empty($errors)) {
        // Handle file upload
        $file_path = '';
        if (isset($_FILES['lesson_file']) && $_FILES['lesson_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/lessons/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['lesson_file']['name'], PATHINFO_EXTENSION));
            $file_name = uniqid() . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;
            
            if (!move_uploaded_file($_FILES['lesson_file']['tmp_name'], $file_path)) {
                $errors[] = "Failed to upload file";
            }
        }
        
        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO lessons (server_id, title, description, file_path, price) VALUES (?, ?, ?, ?, ?)");
            if ($stmt->execute([$server_id, $title, $description, $file_path, $price])) {
                $_SESSION['success'] = "Lesson uploaded successfully!";
            } else {
                $errors[] = "Failed to save lesson. Please try again.";
            }
        }
    }
}

// Get all lessons for this server
$stmt = $conn->prepare("SELECT * FROM lessons WHERE server_id = ? ORDER BY created_at DESC");
$stmt->execute([$server_id]);
$lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Server - Learning Platform</title>
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
                <p><?php echo htmlspecialchars($server['description']); ?></p>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Upload New Lesson</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success">
                                <?php 
                                echo htmlspecialchars($_SESSION['success']);
                                unset($_SESSION['success']);
                                ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="upload_lesson">
                            
                            <div class="mb-3">
                                <label for="title" class="form-label">Lesson Title</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="price" class="form-label">Price (€)</label>
                                <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="lesson_file" class="form-label">Lesson File</label>
                                <input type="file" class="form-control" id="lesson_file" name="lesson_file" required>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Upload Lesson</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Server Lessons</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($lessons)): ?>
                            <p>No lessons uploaded yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Description</th>
                                            <th>Price</th>
                                            <th>Created At</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($lessons as $lesson): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($lesson['title']); ?></td>
                                                <td><?php echo htmlspecialchars($lesson['description']); ?></td>
                                                <td>€<?php echo number_format($lesson['price'], 2); ?></td>
                                                <td><?php echo date('Y-m-d', strtotime($lesson['created_at'])); ?></td>
                                                <td>
                                                    <a href="edit_lesson.php?id=<?php echo $lesson['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                                    <a href="delete_lesson.php?id=<?php echo $lesson['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this lesson?')">Delete</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 