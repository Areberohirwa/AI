<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get user's data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user's servers if they are an instructor
$servers = [];
if ($role === 'provider') {
    $stmt = $conn->prepare("SELECT * FROM servers WHERE provider_id = ?");
    $stmt->execute([$user_id]);
    $servers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get user's subscriptions if they are a student
$subscriptions = [];
if ($role === 'student') {
    $stmt = $conn->prepare("
        SELECT s.*, sr.name as server_name 
        FROM subscriptions s 
        JOIN servers sr ON s.server_id = sr.id 
        WHERE s.user_id = ? AND s.status = 'active'
    ");
    $stmt->execute([$user_id]);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Learning Platform</title>
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
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <h2>Welcome, <?php echo htmlspecialchars($user['username']); ?>!</h2>
                <p>Role: <?php echo ucfirst($role); ?></p>
            </div>
        </div>

        <?php if ($role === 'provider'): ?>
            <!-- Instructor Dashboard -->
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Your Servers</h5>
                            <a href="create_server.php" class="btn btn-primary">Create New Server</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($servers)): ?>
                                <p>You haven't created any servers yet.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Server Name</th>
                                                <th>Description</th>
                                                <th>Monthly Price</th>
                                                <th>Created At</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($servers as $server): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($server['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($server['description']); ?></td>
                                                    <td>€<?php echo number_format($server['monthly_price'], 2); ?></td>
                                                    <td><?php echo date('Y-m-d', strtotime($server['created_at'])); ?></td>
                                                    <td>
                                                        <a href="manage_server.php?id=<?php echo $server['id']; ?>" class="btn btn-sm btn-primary">Manage</a>
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
        <?php else: ?>
            <!-- Student Dashboard -->
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Your Subscriptions</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($subscriptions)): ?>
                                <p>You haven't subscribed to any servers yet.</p>
                                <a href="browse_servers.php" class="btn btn-primary">Browse Servers</a>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Server Name</th>
                                                <th>Start Date</th>
                                                <th>End Date</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($subscriptions as $subscription): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($subscription['server_name']); ?></td>
                                                    <td><?php echo date('Y-m-d', strtotime($subscription['start_date'])); ?></td>
                                                    <td><?php echo date('Y-m-d', strtotime($subscription['end_date'])); ?></td>
                                                    <td><?php echo ucfirst($subscription['status']); ?></td>
                                                    <td>
                                                        <a href="view_server.php?id=<?php echo $subscription['server_id']; ?>" class="btn btn-sm btn-primary">View Server</a>
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
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 