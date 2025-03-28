<?php
require_once 'config/database.php';
require_once 'config/stripe.php';

// Retrieve the raw POST data
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];

try {
    // Verify the webhook signature
    $event = \Stripe\Webhook::constructEvent(
        $payload, $sig_header, STRIPE_WEBHOOK_SECRET
    );
} catch(\UnexpectedValueException $e) {
    http_response_code(400);
    exit();
} catch(\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    exit();
}

// Handle the event
switch ($event->type) {
    case 'customer.subscription.created':
    case 'customer.subscription.updated':
        $subscription = $event->data->object;
        
        // Update subscription status in database
        $stmt = $conn->prepare("
            UPDATE subscriptions 
            SET status = ?, 
                end_date = FROM_UNIXTIME(?)
            WHERE stripe_subscription_id = ?
        ");
        
        $status = $subscription->status === 'active' ? 'active' : 'cancelled';
        $stmt->execute([$status, $subscription->current_period_end, $subscription->id]);
        break;
        
    case 'customer.subscription.deleted':
        $subscription = $event->data->object;
        
        // Update subscription status to expired
        $stmt = $conn->prepare("
            UPDATE subscriptions 
            SET status = 'expired'
            WHERE stripe_subscription_id = ?
        ");
        $stmt->execute([$subscription->id]);
        break;
        
    case 'invoice.payment_succeeded':
        $invoice = $event->data->object;
        
        // Update subscription end date
        $stmt = $conn->prepare("
            UPDATE subscriptions 
            SET end_date = FROM_UNIXTIME(?)
            WHERE stripe_subscription_id = ?
        ");
        $stmt->execute([$invoice->subscription, $invoice->subscription]);
        break;
        
    case 'invoice.payment_failed':
        $invoice = $event->data->object;
        
        // Update subscription status to pending
        $stmt = $conn->prepare("
            UPDATE subscriptions 
            SET status = 'pending'
            WHERE stripe_subscription_id = ?
        ");
        $stmt->execute([$invoice->subscription]);
        break;
}

http_response_code(200);
?> 