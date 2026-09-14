<?php
/**
 * Booking / lead notification endpoint.
 *
 * Receives the booking form's JSON payload, validates it server-side,
 * and emails a notification to the business inbox. No API keys or
 * SMTP credentials are required — this uses PHP's built-in mail()
 * function, which sends through the hosting account's local mail
 * relay on the same domain the site is hosted on.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

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
const MAX_FIELD_LENGTH = 2000;

/**
 * Strip characters that could be used for email header injection and
 * clamp length. Applied to every field before it touches a header or
 * the message body.
 */
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
$paymentMethod = cleanInput((string) ($data['paymentMethod'] ?? ''));

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

$subject = 'New Website Lead — ' . $name;

$body = "New booking request from shutterandspeed.co.nz\n\n"
    . "Name: {$name}\n"
    . "Email: {$email}\n"
    . "Phone: {$phone}\n"
    . "Package: {$package} (\${$price} NZD)\n"
    . "Payment method: {$paymentMethod}\n"
    . "Preferred date: {$date}\n"
    . "Property address: {$address}\n"
    . "Notes: " . ($notes !== '' ? $notes : '(none)') . "\n\n"
    . "Submitted: {$submittedAt}\n"
    . "Source: shutterandspeed.co.nz booking form\n";

$headers = [
    'From: Shutter & Speed Website <' . FROM_EMAIL . '>',
    'Reply-To: ' . $name . ' <' . $email . '>',
    'Content-Type: text/plain; charset=utf-8',
];

$sent = mail(TO_EMAIL, $subject, $body, implode("\r\n", $headers));

if (!$sent) {
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'Could not send the notification email.']);
    exit;
}

// Best-effort customer confirmation. The business notification above
// is the part that matters for this endpoint to report success on —
// if the customer copy fails to send, that's not worth failing the
// visitor's booking submission over.
$customerSubject = 'Booking Received — Shutter & Speed Photography';

$customerBody = "Hi {$name},\n\n"
    . "Thanks for booking with Shutter & Speed Photography! " .
      "We've received your request for the {$package} package (\${$price} NZD).\n\n"
    . "Shoot details\n"
    . "Preferred date: {$date}\n"
    . "Property address: {$address}\n\n"
    . "Payment — Bank Transfer\n"
    . "GAURAV KANT · 01-0071-0937116-00\n"
    . "Please use your name as the payment reference.\n\n"
    . "We'll be in touch shortly to confirm your booking. " .
      "Questions in the meantime? Reply to this email or call 022 124 0224.\n\n"
    . "— Shutter & Speed Photography\n"
    . "shutterandspeed.co.nz\n";

$customerHeaders = [
    'From: Shutter & Speed Photography <' . FROM_EMAIL . '>',
    'Reply-To: Gaurav Kant <' . TO_EMAIL . '>',
    'Content-Type: text/plain; charset=utf-8',
];

mail($email, $customerSubject, $customerBody, implode("\r\n", $customerHeaders));

echo json_encode(['success' => true]);
