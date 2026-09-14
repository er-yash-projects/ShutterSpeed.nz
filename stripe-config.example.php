<?php
/**
 * Template for stripe-config.php — the file that actually holds the
 * secrets is gitignored and deployed directly to Hostinger, never
 * committed. Copy this file to stripe-config.php locally and fill in
 * real values; DO NOT put real secrets in this example file.
 *
 * Where to get each value (Stripe Dashboard, test mode):
 *
 * STRIPE_SECRET_KEY
 *   Developers -> API keys -> Create restricted key.
 *   Scope: "Checkout Sessions" Write, "Products"/"Prices" Read.
 *   Used by create-checkout-session.php to create Checkout Sessions.
 *
 * STRIPE_WEBHOOK_SECRET
 *   Developers -> Webhooks -> Add endpoint
 *     URL: https://shutterandspeed.co.nz/stripe-webhook.php
 *     Event: checkout.session.completed
 *   Then open the endpoint and copy its "Signing secret".
 *   Used by stripe-webhook.php to verify requests actually came from
 *   Stripe before trusting them.
 */

declare(strict_types=1);

define('STRIPE_SECRET_KEY', 'rk_test_...');
define('STRIPE_WEBHOOK_SECRET', 'whsec_...');
