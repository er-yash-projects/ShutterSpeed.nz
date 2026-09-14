<?php
/**
 * Template for stripe-config.php — the file that actually holds the
 * secrets is gitignored and deployed directly to Hostinger, never
 * committed. Copy this file to stripe-config.php locally and fill in
 * real values; DO NOT put real secrets in this example file.
 *
 * Where to get each value (Stripe Dashboard, test mode unless noted):
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
 *
 * CONFIRM_LINK_SECRET
 *   Not related to Stripe. Generate locally with `openssl rand -hex 32`
 *   (or equivalent) -- there's nothing to fetch from a dashboard.
 *   Used by send-booking.php to sign the "Confirm Payment Received"
 *   link in Bank Transfer notification emails, and by
 *   confirm-payment.php to verify that link wasn't forged or altered.
 */

declare(strict_types=1);

define('STRIPE_SECRET_KEY', 'rk_test_...');
define('STRIPE_WEBHOOK_SECRET', 'whsec_...');
define('CONFIRM_LINK_SECRET', '...');
