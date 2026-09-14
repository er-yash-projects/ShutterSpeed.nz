<?php
/**
 * Stripe webhook handler — the source of truth for whether a card
 * payment actually succeeded. The success redirect in the browser
 * is NOT proof of payment (the visitor could close the tab, the
 * network could drop, etc.) — only a signature-verified event from
 * Stripe itself is trusted here.
 *
 * On a verified checkout.session.completed event with payment_status
 * "paid", emails the same lead notification the Bank Transfer flow
 * sends, using the booking details Stripe stored as session metadata.
 */

declare(strict_types=1);

$configPath = __DIR__ . '/stripe-config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    exit('Not configured.');
}
require $configPath;

const TO_EMAIL = 'g.kant1998@gmail.com';
const FROM_EMAIL = 'noreply@shutterandspeed.co.nz';
const SIGNATURE_TOLERANCE_SECONDS = 300;
const PROCESSED_LOG = __DIR__ . '/.stripe-webhook-processed.log';

function verifyStripeSignature(string $payload, string $sigHeader, string $secret): bool
{
    $parts = [];
    foreach (explode(',', $sigHeader) as $pair) {
        [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
        $parts[$key][] = $value;
    }

    $timestamp = $parts['t'][0] ?? '';
    $signatures = $parts['v1'] ?? [];

    if ($timestamp === '' || empty($signatures)) {
        return false;
    }

    if (abs(time() - (int) $timestamp) > SIGNATURE_TOLERANCE_SECONDS) {
        return false;
    }

    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

    foreach ($signatures as $signature) {
        if (hash_equals($expected, $signature)) {
            return true;
        }
    }

    return false;
}

/**
 * Stripe retries webhooks that don't return 2xx, and can occasionally
 * deliver the same event more than once even after a 2xx. This keeps
 * a flat log of already-handled session IDs so a retried or
 * duplicated delivery never sends a second notification email.
 */
function alreadyProcessed(string $sessionId): bool
{
    if (!file_exists(PROCESSED_LOG)) {
        return false;
    }

    $handle = fopen(PROCESSED_LOG, 'r');
    if ($handle === false) {
        return false;
    }

    while (($line = fgets($handle)) !== false) {
        if (trim($line) === $sessionId) {
            fclose($handle);
            return true;
        }
    }

    fclose($handle);
    return false;
}

function markProcessed(string $sessionId): void
{
    file_put_contents(PROCESSED_LOG, $sessionId . "\n", FILE_APPEND | LOCK_EX);
}

function cleanForEmail(string $value): string
{
    return str_replace(["\r", "\n"], ' ', trim($value));
}

$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if ($payload === false || $payload === '' || $sigHeader === '') {
    http_response_code(400);
    exit('Missing payload or signature.');
}

if (!verifyStripeSignature($payload, $sigHeader, STRIPE_WEBHOOK_SECRET)) {
    http_response_code(400);
    exit('Signature verification failed.');
}

$event = json_decode($payload, true);

if (!is_array($event) || !isset($event['type'])) {
    http_response_code(400);
    exit('Invalid event payload.');
}

// Any other event type: acknowledge so Stripe doesn't retry, but do
// nothing — this endpoint only cares about completed checkouts.
if ($event['type'] !== 'checkout.session.completed') {
    http_response_code(200);
    exit('Ignored.');
}

$session = $event['data']['object'] ?? [];
$sessionId = (string) ($session['id'] ?? '');
$paymentStatus = (string) ($session['payment_status'] ?? '');

if ($sessionId === '') {
    http_response_code(400);
    exit('Missing session id.');
}

if ($paymentStatus !== 'paid') {
    // Completed but not actually paid yet (e.g. a delayed payment
    // method) — nothing to notify about until a later event confirms
    // payment.
    http_response_code(200);
    exit('Not paid yet.');
}

if (alreadyProcessed($sessionId)) {
    http_response_code(200);
    exit('Already processed.');
}

$metadata = $session['metadata'] ?? [];

$name = cleanForEmail((string) ($metadata['name'] ?? 'Unknown'));
$phone = cleanForEmail((string) ($metadata['phone'] ?? ''));
$date = cleanForEmail((string) ($metadata['date'] ?? ''));
$address = cleanForEmail((string) ($metadata['address'] ?? ''));
$notes = cleanForEmail((string) ($metadata['notes'] ?? ''));
$package = cleanForEmail((string) ($metadata['package'] ?? ''));
$email = cleanForEmail((string) ($session['customer_email'] ?? $session['customer_details']['email'] ?? ''));

$amountTotal = (int) ($session['amount_total'] ?? 0);
$currency = strtoupper((string) ($session['currency'] ?? 'nzd'));
$amountFormatted = number_format($amountTotal / 100, 2);

$submittedAt = date('Y-m-d H:i:s T');

$subject = 'New Website Lead — ' . $name . ' (Paid via Stripe)';

$body = "New PAID booking from shutterandspeed.co.nz\n\n"
    . "Name: {$name}\n"
    . "Email: {$email}\n"
    . "Phone: {$phone}\n"
    . "Package: {$package}\n"
    . "Amount paid: {$amountFormatted} {$currency}\n"
    . "Preferred date: {$date}\n"
    . "Property address: {$address}\n"
    . "Notes: " . ($notes !== '' ? $notes : '(none)') . "\n\n"
    . "Stripe session: {$sessionId}\n"
    . "Received: {$submittedAt}\n"
    . "Source: shutterandspeed.co.nz Stripe checkout\n";

$headers = [
    'From: Shutter & Speed Website <' . FROM_EMAIL . '>',
    'Content-Type: text/plain; charset=utf-8',
];

if ($email !== '') {
    $headers[] = 'Reply-To: ' . $name . ' <' . $email . '>';
}

mail(TO_EMAIL, $subject, $body, implode("\r\n", $headers));

markProcessed($sessionId);

http_response_code(200);
echo 'OK';
