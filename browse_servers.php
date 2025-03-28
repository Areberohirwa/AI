<?php
session_start();
require_once 'config/database.php';
require_once 'config/stripe.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

// Get all servers with subscription status
$stmt = $conn->prepare("
    SELECT s.*, 
           CASE WHEN sub.status = 'active' THEN 1 ELSE 0 END as is_subscribed
    FROM servers s
    LEFT JOIN subscriptions sub ON s.id = sub.server_id 
        AND sub.user_id = ? AND sub.status = 'active'
    ORDER BY is_subscribed DESC, s.name ASC
");
$stmt->execute([$_SESSION['user_id']]);
$servers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Servers - Learning Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://js.stripe.com/v3/"></script>
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
        <h2>Available Servers</h2>
        <div class="row">
            <?php foreach ($servers as $server): ?>
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($server['name']); ?></h5>
                            <p class="card-text"><?php echo htmlspecialchars($server['description']); ?></p>
                            <p class="card-text">
                                <small class="text-muted">
                                    Monthly Fee: €<?php echo number_format($server['monthly_fee'], 2); ?>
                                </small>
                            </p>
                            <?php if ($server['is_subscribed']): ?>
                                <a href="view_server.php?id=<?php echo $server['id']; ?>" class="btn btn-primary">View Server</a>
                            <?php else: ?>
                                <button class="btn btn-success" onclick="showSubscriptionModal(<?php echo $server['id']; ?>)">
                                    Subscribe
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Subscription Modal -->
    <div class="modal fade" id="subscriptionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Subscribe to Server</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="subscription-form">
                        <input type="hidden" id="server_id" name="server_id">
                        <div id="card-element" class="form-control mb-3"></div>
                        <div id="card-errors" class="alert alert-danger d-none"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="submit-subscription">Subscribe</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const stripe = Stripe('<?php echo STRIPE_PUBLISHABLE_KEY; ?>');
        const elements = stripe.elements();
        const card = elements.create('card');
        card.mount('#card-element');

        const modal = new bootstrap.Modal(document.getElementById('subscriptionModal'));
        const form = document.getElementById('subscription-form');
        const submitButton = document.getElementById('submit-subscription');
        const cardErrors = document.getElementById('card-errors');

        function showSubscriptionModal(serverId) {
            document.getElementById('server_id').value = serverId;
            modal.show();
        }

        card.on('change', function(event) {
            if (event.error) {
                cardErrors.textContent = event.error.message;
                cardErrors.classList.remove('d-none');
            } else {
                cardErrors.classList.add('d-none');
            }
        });

        submitButton.addEventListener('click', async function(event) {
            event.preventDefault();
            submitButton.disabled = true;

            try {
                const response = await fetch('process_subscription.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams(new FormData(form))
                });

                const data = await response.json();

                if (data.error) {
                    throw new Error(data.error);
                }

                const result = await stripe.confirmCardPayment(data.clientSecret, {
                    payment_method: {
                        card: card,
                        billing_details: {
                            email: '<?php echo $_SESSION['email']; ?>'
                        }
                    }
                });

                if (result.error) {
                    throw new Error(result.error.message);
                }

                // Payment successful
                modal.hide();
                location.reload();
            } catch (error) {
                cardErrors.textContent = error.message;
                cardErrors.classList.remove('d-none');
                submitButton.disabled = false;
            }
        });
    </script>
</body>
</html> 