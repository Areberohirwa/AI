<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

// Check if server ID is provided
if (!isset($_GET['id'])) {
    header("Location: browse_servers.php");
    exit();
}

$server_id = $_GET['id'];

// Get server details
$stmt = $conn->prepare("SELECT * FROM servers WHERE id = ?");
$stmt->execute([$server_id]);
$server = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$server) {
    header("Location: browse_servers.php");
    exit();
}

// Check if user is already subscribed
$stmt = $conn->prepare("SELECT * FROM subscriptions WHERE user_id = ? AND server_id = ? AND status = 'active'");
$stmt->execute([$_SESSION['user_id'], $server_id]);
if ($stmt->rowCount() > 0) {
    header("Location: view_server.php?id=" . $server_id);
    exit();
}

// Handle Stripe payment
require_once 'vendor/autoload.php';
\Stripe\Stripe::setApiKey('your_stripe_secret_key');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Create Stripe customer if not exists
        $stmt = $conn->prepare("SELECT stripe_customer_id FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user['stripe_customer_id']) {
            $customer = \Stripe\Customer::create([
                'email' => $_SESSION['email'],
                'source' => $_POST['stripeToken']
            ]);
            
            $stmt = $conn->prepare("UPDATE users SET stripe_customer_id = ? WHERE id = ?");
            $stmt->execute([$customer->id, $_SESSION['user_id']]);
        } else {
            $customer = \Stripe\Customer::retrieve($user['stripe_customer_id']);
        }
        
        // Create subscription
        $subscription = \Stripe\Subscription::create([
            'customer' => $customer->id,
            'items' => [['price' => $server['stripe_price_id']]],
            'payment_behavior' => 'default_incomplete',
            'expand' => ['latest_invoice.payment_intent'],
        ]);
        
        // Store subscription details in database
        $stmt = $conn->prepare("INSERT INTO subscriptions (user_id, server_id, stripe_subscription_id, status, start_date, end_date) VALUES (?, ?, ?, 'active', NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH))");
        if ($stmt->execute([$_SESSION['user_id'], $server_id, $subscription->id])) {
            $_SESSION['success'] = "Successfully subscribed to " . htmlspecialchars($server['name']);
            header("Location: view_server.php?id=" . $server_id);
            exit();
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscribe - Learning Platform</title>
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
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-center">Subscribe to <?php echo htmlspecialchars($server['name']); ?></h3>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger">
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-4">
                            <h5>Server Details</h5>
                            <p><?php echo htmlspecialchars($server['description']); ?></p>
                            <p><strong>Monthly Price:</strong> €<?php echo number_format($server['monthly_price'], 2); ?></p>
                        </div>
                        
                        <form method="POST" action="" id="payment-form">
                            <div class="mb-3">
                                <label for="card-element" class="form-label">Credit or debit card</label>
                                <div id="card-element" class="form-control">
                                    <!-- Stripe Card Element will be inserted here -->
                                </div>
                                <div id="card-errors" class="invalid-feedback" role="alert"></div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary" id="submit-button">
                                    Subscribe Now
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Stripe
        const stripe = Stripe('your_stripe_publishable_key');
        const elements = stripe.elements();
        
        // Create card Element and mount it
        const card = elements.create('card');
        card.mount('#card-element');
        
        // Handle form submission
        const form = document.getElementById('payment-form');
        const submitButton = document.getElementById('submit-button');
        
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            submitButton.disabled = true;
            
            const {token, error} = await stripe.createToken(card);
            
            if (error) {
                const errorElement = document.getElementById('card-errors');
                errorElement.textContent = error.message;
                submitButton.disabled = false;
            } else {
                // Add token to form
                const hiddenInput = document.createElement('input');
                hiddenInput.setAttribute('type', 'hidden');
                hiddenInput.setAttribute('name', 'stripeToken');
                hiddenInput.setAttribute('value', token.id);
                form.appendChild(hiddenInput);
                
                // Submit form
                form.submit();
            }
        });
    </script>
</body>
</html> 