<?php
/**
 * Pre-booking inquiry endpoint.
 *
 * Receives the "Ask a question" form's JSON payload, validates it
 * server-side, and emails it to the business inbox. Same approach as
 * send-booking.php: PHP's built-in mail() through the hosting
 * account's local mail relay, no API keys or SMTP credentials needed.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/email-template.php';

// This endpoint is only ever called from the site's own inquiry form.
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
// Message is the one field long enough to want its newlines preserved
// for display, so it's cleaned separately from cleanInput() (which
// flattens newlines — appropriate for header-adjacent fields, not a
// paragraph of text). eMultiline() below turns the newlines into <br>
// when it's placed in the HTML body.
$rawMessage = (string) ($data['message'] ?? '');
$message = mb_substr(trim($rawMessage), 0, MAX_FIELD_LENGTH);

$errors = [];

if ($name === '') {
    $errors[] = 'Name is required.';
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid email address is required.';
}

if ($message === '') {
    $errors[] = 'Please enter your question or message.';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => implode(' ', $errors)]);
    exit;
}

$submittedAt = date('Y-m-d H:i:s T');

$businessBody = '<p>New inquiry from the website.</p>'
    . '<p><strong>Message:</strong><br>' . eMultiline($message) . '</p>';

$businessHtml = renderEmailHtml(
    'New Inquiry',
    'New Website Inquiry',
    $businessBody,
    [
        'Name' => $name,
        'Email' => $email,
        'Submitted' => $submittedAt,
    ]
);

$headers = [
    'From: Shutter & Speed Website <' . FROM_EMAIL . '>',
    'Reply-To: ' . $name . ' <' . $email . '>',
];

$sent = sendHtmlEmail(TO_EMAIL, 'New Website Inquiry — ' . $name, $businessHtml, $headers);

if (!$sent) {
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'Could not send your message.']);
    exit;
}

// Best-effort auto-reply. The business notification above is what
// matters for this endpoint to report success on — if the visitor's
// copy fails to send, that's not worth failing their submission over.
$customerBody = '<p>Hi ' . e($name) . ',</p>'
    . '<p>Thanks for reaching out to Shutter &amp; Speed Photography — '
    . 'we\'ve received your message and will reply soon.</p>'
    . '<p><strong>Your message:</strong><br>' . eMultiline($message) . '</p>'
    . '<p>In a hurry? Call or WhatsApp Gaurav directly on 022 124 0224.</p>';

$customerHtml = renderEmailHtml(
    'Message Received',
    "We've got your question",
    $customerBody
);

$customerHeaders = [
    'From: Shutter & Speed Photography <' . FROM_EMAIL . '>',
    'Reply-To: Gaurav Kant <' . TO_EMAIL . '>',
];

sendHtmlEmail($email, "We've got your question — Shutter & Speed Photography", $customerHtml, $customerHeaders);

echo json_encode(['success' => true]);
