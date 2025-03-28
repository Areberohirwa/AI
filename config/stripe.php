<?php
// Stripe API Configuration
define('STRIPE_SECRET_KEY', 'your_stripe_secret_key');
define('STRIPE_PUBLISHABLE_KEY', 'your_stripe_publishable_key');

// Currency settings
define('STRIPE_CURRENCY', 'eur');

// Webhook secret for handling Stripe events
define('STRIPE_WEBHOOK_SECRET', 'your_webhook_secret');

// Initialize Stripe
require_once __DIR__ . '/../vendor/autoload.php';
\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
?> 