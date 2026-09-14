<?php
/**
 * Gaurav-facing page for confirming a Bank Transfer payment was
 * actually received, reached only via the signed link in his own
 * "New Booking — Awaiting Payment" email. Nobody else can construct
 * a valid link (the booking details are HMAC-signed), so the
 * signature itself is the access control here — no login needed.
 *
 * GET verifies the signature and shows the booking details with a
 * confirm button. The actual customer email only sends on the
 * follow-up POST — deliberately split in two so an email-security
 * scanner "previewing" the GET link can't accidentally trigger a
 * false confirmation before Gaurav has checked his bank account.
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
const CONFIRMED_LOG = __DIR__ . '/.bank-transfer-confirmed.log';

function pageShell(string $title, string $bodyHtml): string
{
    return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . e($title) . '</title>
<style>
  body { background:#050505; color:#f5f5f2; font-family:Arial,Helvetica,sans-serif; margin:0; padding:40px 20px; }
  .box { max-width:560px; margin:0 auto; background:#111111; border-top:4px solid #d7ff00; padding:32px; }
  h1 { font-size:22px; margin:0 0 20px; }
  table { width:100%; border-collapse:collapse; margin:20px 0; }
  td { padding:10px 0; border-top:1px solid #262626; font-size:14px; vertical-align:top; }
  td.label { color:#888; font-size:11px; font-weight:700; letter-spacing:1px; text-transform:uppercase; width:140px; }
  td.value { color:#f5f5f2; }
  button { background:#d7ff00; color:#000; border:0; padding:14px 28px; font-size:13px; font-weight:700; letter-spacing:1px; text-transform:uppercase; cursor:pointer; }
  p { color:#ccc; font-size:14px; line-height:1.6; }
</style>
</head>
<body>
<div class="box">' . $bodyHtml . '</div>
</body>
</html>';
}

function errorPage(string $message): void
{
    http_response_code(400);
    echo pageShell('Link Error', '<h1>' . e($message) . '</h1><p>This confirmation link is invalid or has been tampered with.</p>');
    exit;
}

function idempotencyKey(string $data, string $sig): string
{
    return hash('sha256', $data . '.' . $sig);
}

function alreadyConfirmed(string $key): bool
{
    if (!file_exists(CONFIRMED_LOG)) {
        return false;
    }

    $handle = fopen(CONFIRMED_LOG, 'r');
    if ($handle === false) {
        return false;
    }

    while (($line = fgets($handle)) !== false) {
        if (trim($line) === $key) {
            fclose($handle);
            return true;
        }
    }

    fclose($handle);
    return false;
}

function markConfirmed(string $key): void
{
    file_put_contents(CONFIRMED_LOG, $key . "\n", FILE_APPEND | LOCK_EX);
}

$method = $_SERVER['REQUEST_METHOD'];
$data = (string) ($method === 'POST' ? ($_POST['data'] ?? '') : ($_GET['data'] ?? ''));
$sig = (string) ($method === 'POST' ? ($_POST['sig'] ?? '') : ($_GET['sig'] ?? ''));

if ($data === '' || $sig === '') {
    errorPage('Missing link data.');
}

$fields = verifyBookingPayload($data, $sig, CONFIRM_LINK_SECRET);

if ($fields === null) {
    errorPage('Invalid confirmation link.');
}

$name = (string) ($fields['name'] ?? '');
$email = (string) ($fields['email'] ?? '');
$phone = (string) ($fields['phone'] ?? '');
$date = (string) ($fields['date'] ?? '');
$address = (string) ($fields['address'] ?? '');
$notes = (string) ($fields['notes'] ?? '');
$package = (string) ($fields['package'] ?? '');
$price = (string) ($fields['price'] ?? '');

$key = idempotencyKey($data, $sig);

$detailsRows = '
    <tr><td class="label">Name</td><td class="value">' . e($name) . '</td></tr>
    <tr><td class="label">Email</td><td class="value">' . e($email) . '</td></tr>
    <tr><td class="label">Phone</td><td class="value">' . e($phone) . '</td></tr>
    <tr><td class="label">Package</td><td class="value">' . e($package) . ' ($' . e($price) . ' NZD)</td></tr>
    <tr><td class="label">Shoot date</td><td class="value">' . e($date) . '</td></tr>
    <tr><td class="label">Address</td><td class="value">' . e($address) . '</td></tr>
    <tr><td class="label">Notes</td><td class="value">' . ($notes !== '' ? eMultiline($notes) : '(none)') . '</td></tr>
';

if ($method === 'POST') {

    if (alreadyConfirmed($key)) {
        echo pageShell('Already Confirmed', '<h1>Already confirmed</h1><p>This booking was already marked as confirmed and ' . e($name) . ' has already been emailed.</p>');
        exit;
    }

    if ($email !== '') {

        $bodyHtml = '<p>Hi ' . e($name) . ',</p>'
            . '<p>Good news — we\'ve received your bank transfer and your shoot is now <strong>confirmed</strong>.</p>';

        $html = renderEmailHtml(
            'Booking Confirmed',
            'Your shoot is locked in',
            $bodyHtml,
            [
                'Package' => $package . ' ($' . $price . ' NZD)',
                'Shoot date' => $date,
                'Property address' => $address,
            ]
        );

        $headers = [
            'From: Shutter & Speed Photography <' . FROM_EMAIL . '>',
            'Reply-To: Gaurav Kant <' . TO_EMAIL . '>',
        ];

        sendHtmlEmail($email, 'Booking Confirmed — Shutter & Speed Photography', $html, $headers);

    }

    markConfirmed($key);

    echo pageShell('Confirmed', '<h1>&#10003; Payment confirmed</h1><p>We\'ve emailed ' . e($name) . ' at ' . e($email) . ' to let them know their shoot is confirmed.</p>');
    exit;

}

// GET: show the booking details and a confirm button. No email sent yet.
$alreadyDone = alreadyConfirmed($key);

$actionHtml = $alreadyDone
    ? '<p><strong>This booking has already been confirmed.</strong></p>'
    : '<form method="POST">'
        . '<input type="hidden" name="data" value="' . e($data) . '">'
        . '<input type="hidden" name="sig" value="' . e($sig) . '">'
        . '<button type="submit">Confirm &amp; Notify Customer</button>'
        . '</form>';

echo pageShell(
    'Confirm Payment',
    '<h1>Confirm bank transfer received?</h1>'
    . '<p>Check this matches what landed in your account before confirming — this sends ' . e($name) . ' a "Booking Confirmed" email.</p>'
    . '<table>' . $detailsRows . '</table>'
    . $actionHtml
);
