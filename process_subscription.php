<?php
require_once 'config/database.php';
require_once 'config/stripe.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $server_id = $_POST['server_id'];
    $user_id = $_SESSION['user_id'];
    
    // Get server details
    $stmt = $conn->prepare("SELECT * FROM servers WHERE id = ?");
    $stmt->execute([$server_id]);
    $server = $stmt->fetch();
    
    if (!$server) {
        $_SESSION['error'] = "Server not found.";
        header("Location: browse_servers.php");
        exit();
    }
    
    try {
        // Check if user already has a Stripe customer ID
        $stmt = $conn->prepare("SELECT stripe_customer_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user['stripe_customer_id']) {
            // Create a new Stripe customer
            $customer = \Stripe\Customer::create([
                'email' => $_SESSION['email'],
                'metadata' => [
                    'user_id' => $user_id
                ]
            ]);
            
            // Save the Stripe customer ID
            $stmt = $conn->prepare("UPDATE users SET stripe_customer_id = ? WHERE id = ?");
            $stmt->execute([$customer->id, $user_id]);
        } else {
            $customer = \Stripe\Customer::retrieve($user['stripe_customer_id']);
        }
        
        // Create a Stripe subscription
        $subscription = \Stripe\Subscription::create([
            'customer' => $customer->id,
            'items' => [[
                'price' => $server['stripe_price_id'],
                'quantity' => 1,
            ]],
            'payment_behavior' => 'default_incomplete',
            'expand' => ['latest_invoice.payment_intent'],
            'metadata' => [
                'server_id' => $server_id,
                'user_id' => $user_id
            ]
        ]);
        
        // Save subscription details to database
        $stmt = $conn->prepare("
            INSERT INTO subscriptions (user_id, server_id, status, stripe_subscription_id, start_date, end_date)
            VALUES (?, ?, 'pending', ?, NOW(), FROM_UNIXTIME(?))
        ");
        $stmt->execute([
            $user_id,
            $server_id,
            $subscription->id,
            $subscription->current_period_end
        ]);
        
        // Return the client secret for the payment intent
        echo json_encode([
            'clientSecret' => $subscription->latest_invoice->payment_intent->client_secret
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    header("Location: browse_servers.php");
    exit();
}
?> 