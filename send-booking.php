<?php
/**
 * Bank Transfer booking endpoint.
 *
 * Receives the booking form's JSON payload, validates it server-side,
 * and emails both sides — but a Bank Transfer booking is NOT
 * confirmed just by submitting the form. There's no bank webhook to
 * verify the money actually arrived (unlike the Stripe card flow),
 * so the customer is told their booking is pending, and Gaurav gets
 * a signed "Confirm Payment Received" link he only clicks after
 * checking his bank account himself (see confirm-payment.php).
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$configPath = __DIR__ . '/stripe-config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Not configured.']);
    exit;
}
require $configPath;
require __DIR__ . '/email-template.php';

// This endpoint is only ever called from the site's own booking form.
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

const TO_EMAIL = 'g.kant1998@gmail.com';
const FROM_EMAIL = 'noreply@shutterandspeed.co.nz';

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request body.']);
    exit;
}

// Honeypot: a hidden field real visitors never fill in. Any value here
// means a bot filled every field on the form — silently accept the
// request without sending an email so the bot doesn't learn it failed.
if (!empty($data['company'])) {
    echo json_encode(['success' => true]);
    exit;
}

$name = cleanInput((string) ($data['name'] ?? ''));
$email = cleanInput((string) ($data['email'] ?? ''));
$phone = cleanInput((string) ($data['phone'] ?? ''));
$date = cleanInput((string) ($data['date'] ?? ''));
$address = cleanInput((string) ($data['address'] ?? ''));
$notes = cleanInput((string) ($data['notes'] ?? ''));
$package = cleanInput((string) ($data['package'] ?? ''));
$price = cleanInput((string) ($data['price'] ?? ''));

$errors = [];

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

$submittedAt = date('Y-m-d H:i:s T');

// Signed link Gaurav clicks once he's checked his bank account and
// actually sees the transfer — see confirm-payment.php. Nobody else
// can construct a valid signature for a different booking's details.
$confirmPayload = signBookingPayload([
    'name' => $name,
    'email' => $email,
    'phone' => $phone,
    'date' => $date,
    'address' => $address,
    'notes' => $notes,
    'package' => $package,
    'price' => $price,
], CONFIRM_LINK_SECRET);

$confirmUrl = 'https://shutterandspeed.co.nz/confirm-payment.php?'
    . http_build_query($confirmPayload);

$businessBody = '<p>New Bank Transfer booking — <strong>not yet paid</strong>. '
    . 'Check your account, then use the button below once you see the transfer land.</p>';

$businessHtml = renderEmailHtml(
    'Awaiting Payment',
    'New Booking Request',
    $businessBody,
    [
        'Name' => $name,
        'Email' => $email,
        'Phone' => $phone,
        'Package' => $package . ' ($' . $price . ' NZD)',
        'Preferred date' => $date,
        'Property address' => $address,
        'Notes' => $notes !== '' ? $notes : '(none)',
        'Submitted' => $submittedAt,
    ],
    ['label' => 'Confirm Payment Received', 'url' => $confirmUrl]
);

$headers = [
    'From: Shutter & Speed Website <' . FROM_EMAIL . '>',
    'Reply-To: ' . $name . ' <' . $email . '>',
];

$sent = sendHtmlEmail(TO_EMAIL, 'New Booking — Awaiting Payment (' . $name . ')', $businessHtml, $headers);

if (!$sent) {
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'Could not send the notification email.']);
    exit;
}

// Best-effort customer email. The business notification above is
// what matters for this endpoint to report success on — if this
// copy fails to send, that's not worth failing the visitor's
// submission over.
$customerBody = '<p>Hi ' . e($name) . ',</p>'
    . '<p>Thanks for booking with Shutter &amp; Speed Photography! '
    . 'We\'ve received your request for the ' . e($package) . ' package — '
    . '<strong>your shoot isn\'t confirmed yet</strong>, though. '
    . 'We\'re waiting to see your bank transfer land, and you\'ll get a follow-up email '
    . 'the moment we do.</p>'
    . '<p><strong>Please transfer $' . e($price) . ' NZD to:</strong><br>'
    . 'GAURAV KANT · 01-0071-0937116-00<br>'
    . 'Reference: ' . e($name) . '</p>';

$customerHtml = renderEmailHtml(
    'Payment Pending',
    'Booking Received — Not Yet Confirmed',
    $customerBody,
    [
        'Package' => $package . ' ($' . $price . ' NZD)',
        'Preferred date' => $date,
        'Property address' => $address,
    ]
);

$customerHeaders = [
    'From: Shutter & Speed Photography <' . FROM_EMAIL . '>',
    'Reply-To: Gaurav Kant <' . TO_EMAIL . '>',
];

sendHtmlEmail($email, 'Booking Received — Payment Pending', $customerHtml, $customerHeaders);

echo json_encode(['success' => true]);
