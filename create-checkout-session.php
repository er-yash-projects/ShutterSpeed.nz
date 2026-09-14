<?php
/**
 * Creates a Stripe Checkout Session for a one-time package payment
 * and returns its hosted URL. The frontend redirects the browser
 * there; Stripe handles card entry entirely on its own hosted page.
 *
 * A successful redirect back to this site is NOT proof the payment
 * succeeded — that confirmation comes from a verified Stripe webhook
 * (see the webhook handler), not from this endpoint or the redirect.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$configPath = __DIR__ . '/stripe-config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Payment is not configured yet.']);
    exit;
}
require $configPath;

$allowedOrigin = 'https://shutterandspeed.co.nz';
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin === $allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

const MAX_FIELD_LENGTH = 2000;

// Package -> Stripe Price ID. Prices themselves (amount, currency)
// live in Stripe, not here, so the client can never influence what
// gets charged — only which of these three fixed prices applies.
const PACKAGE_PRICES = [
    'Essential' => 'price_1UFSswDxJITga753Irhx0HcG',
    'Professional' => 'price_1UFSswDxJITga753pc31Uxc3',
    'Premium' => 'price_1UFSsxDxJITga753stUYJoz7',
];

function cleanInput(string $value): string
{
    $value = str_replace(["\r", "\n"], ' ', $value);
    $value = trim($value);
    return mb_substr($value, 0, MAX_FIELD_LENGTH);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request body.']);
    exit;
}

// Honeypot — same spam trap as the lead-email endpoint.
if (!empty($data['company'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Request rejected.']);
    exit;
}

$package = cleanInput((string) ($data['package'] ?? ''));
$name = cleanInput((string) ($data['name'] ?? ''));
$email = cleanInput((string) ($data['email'] ?? ''));
$phone = cleanInput((string) ($data['phone'] ?? ''));
$date = cleanInput((string) ($data['date'] ?? ''));
$address = cleanInput((string) ($data['address'] ?? ''));
$notes = cleanInput((string) ($data['notes'] ?? ''));

$errors = [];

if (!array_key_exists($package, PACKAGE_PRICES)) {
    $errors[] = 'Please choose a valid package.';
}

if ($name === '') {
    $errors[] = 'Name is required.';
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid email address is required.';
}

if ($phone === '') {
    $errors[] = 'Phone number is required.';
}

if ($address === '') {
    $errors[] = 'Property address is required.';
}

if ($date === '') {
    $errors[] = 'Preferred shoot date is required.';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => implode(' ', $errors)]);
    exit;
}

$priceId = PACKAGE_PRICES[$package];

$params = [
    'mode' => 'payment',
    'line_items[0][price]' => $priceId,
    'line_items[0][quantity]' => '1',
    'success_url' => $allowedOrigin . '/?checkout=success&session_id={CHECKOUT_SESSION_ID}',
    'cancel_url' => $allowedOrigin . '/?checkout=cancelled',
    'customer_email' => $email,
    'metadata[package]' => $package,
    'metadata[name]' => $name,
    'metadata[phone]' => $phone,
    'metadata[date]' => $date,
    'metadata[address]' => $address,
    'metadata[notes]' => $notes,
];

$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($params),
    CURLOPT_USERPWD => STRIPE_SECRET_KEY . ':',
    CURLOPT_TIMEOUT => 15,
]);

$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'Could not reach Stripe: ' . $curlError]);
    exit;
}

$session = json_decode($response, true);

if ($status >= 400 || !isset($session['url'])) {
    http_response_code(502);
    $stripeError = $session['error']['message'] ?? 'Stripe request failed.';
    echo json_encode(['success' => false, 'error' => $stripeError]);
    exit;
}

echo json_encode(['success' => true, 'url' => $session['url']]);
