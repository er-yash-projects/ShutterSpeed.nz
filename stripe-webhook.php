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
 * Unlike Bank Transfer, no manual confirm step is needed here — the
 * webhook itself is the verified proof of payment.
 */

declare(strict_types=1);

$configPath = __DIR__ . '/stripe-config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    exit('Not configured.');
}
require $configPath;
require __DIR__ . '/email-template.php';

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

$name = cleanInput((string) ($metadata['name'] ?? 'Unknown'));
$phone = cleanInput((string) ($metadata['phone'] ?? ''));
$date = cleanInput((string) ($metadata['date'] ?? ''));
$address = cleanInput((string) ($metadata['address'] ?? ''));
$notes = cleanInput((string) ($metadata['notes'] ?? ''));
$package = cleanInput((string) ($metadata['package'] ?? ''));
$email = cleanInput((string) ($session['customer_email'] ?? $session['customer_details']['email'] ?? ''));

$amountTotal = (int) ($session['amount_total'] ?? 0);
$currency = strtoupper((string) ($session['currency'] ?? 'nzd'));
$amountFormatted = number_format($amountTotal / 100, 2);

$submittedAt = date('Y-m-d H:i:s T');

$businessBody = '<p>New booking, <strong>already paid</strong> via Stripe — no action needed, this is confirmed.</p>';

$businessHtml = renderEmailHtml(
    'Paid via Stripe',
    'New Booking — Confirmed',
    $businessBody,
    [
        'Name' => $name,
        'Email' => $email,
        'Phone' => $phone,
        'Package' => $package,
        'Amount paid' => $amountFormatted . ' ' . $currency,
        'Preferred date' => $date,
        'Property address' => $address,
        'Notes' => $notes !== '' ? $notes : '(none)',
        'Stripe session' => $sessionId,
        'Received' => $submittedAt,
    ]
);

$headers = [
    'From: Shutter & Speed Website <' . FROM_EMAIL . '>',
];

if ($email !== '') {
    $headers[] = 'Reply-To: ' . $name . ' <' . $email . '>';
}

sendHtmlEmail(TO_EMAIL, 'New Website Lead — ' . $name . ' (Paid via Stripe)', $businessHtml, $headers);

// Customer confirmation. Best-effort: the business notification above
// is what matters for this webhook to have "handled" the event —
// mail() delivery to the customer isn't checked before marking the
// session processed, same as the Bank Transfer flow's customer copy.
if ($email !== '') {

    $customerBody = '<p>Hi ' . e($name) . ',</p>'
        . '<p>Thanks for booking with Shutter &amp; Speed Photography! '
        . 'Your payment has gone through and your booking is <strong>confirmed</strong>.</p>';

    $customerHtml = renderEmailHtml(
        'Payment Confirmed',
        'Your shoot is locked in',
        $customerBody,
        [
            'Package' => $package,
            'Amount paid' => $amountFormatted . ' ' . $currency,
            'Preferred date' => $date,
            'Property address' => $address,
        ]
    );

    $customerHeaders = [
        'From: Shutter & Speed Photography <' . FROM_EMAIL . '>',
        'Reply-To: Gaurav Kant <' . TO_EMAIL . '>',
    ];

    sendHtmlEmail($email, 'Payment Confirmed — Shutter & Speed Photography', $customerHtml, $customerHeaders);

}

markProcessed($sessionId);

http_response_code(200);
echo 'OK';
