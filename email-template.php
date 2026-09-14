<?php
/**
 * Shared helpers for every outgoing email on this site: input
 * cleaning, HTML escaping, the branded HTML shell, and a small
 * mail() wrapper. Required by send-booking.php, stripe-webhook.php,
 * ask-question.php, and confirm-payment.php so all five emails look
 * and behave consistently instead of each file rolling its own.
 */

declare(strict_types=1);

const MAX_FIELD_LENGTH = 2000;

/**
 * Strip characters that could be used for email header injection and
 * clamp length. Applied to every field before it touches a header.
 * (Not a substitute for e() below — this makes a value safe for a
 * header line; e() makes it safe to place inside HTML.)
 */
function cleanInput(string $value): string
{
    $value = str_replace(["\r", "\n"], ' ', $value);
    $value = trim($value);
    return mb_substr($value, 0, MAX_FIELD_LENGTH);
}

/**
 * Escape a value for safe interpolation into the HTML email body.
 * Every piece of visitor-supplied text (name, address, notes,
 * message, ...) MUST go through this before being placed in HTML —
 * unlike the old plain-text emails, an HTML email can be an
 * injection vector if a field like "name" isn't escaped.
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Same as e(), but preserves line breaks (for the multi-line
 * "message" / "notes" fields) as <br> instead of collapsing them.
 */
function eMultiline(string $value): string
{
    return nl2br(e($value));
}

/**
 * Assembles the branded HTML shell around whatever content a caller
 * supplies. Uses inline styles throughout (not a <style> block) for
 * maximum email-client compatibility.
 *
 * @param string $eyebrow Small uppercase label above the heading, e.g. "Booking Pending"
 * @param string $heading Main heading, plain text (escaped internally)
 * @param string $bodyHtml Pre-built, pre-escaped HTML for the body paragraphs
 * @param array<string,string> $details Optional label => value rows (values are escaped internally)
 * @param array{label:string,url:string}|null $cta Optional call-to-action button
 */
function renderEmailHtml(
    string $eyebrow,
    string $heading,
    string $bodyHtml,
    array $details = [],
    ?array $cta = null
): string {

    $detailsHtml = '';
    if (!empty($details)) {
        $rows = '';
        foreach ($details as $label => $value) {
            $rows .= '
                <tr>
                    <td style="padding:10px 0;border-top:1px solid #262626;color:#888;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;vertical-align:top;width:140px;">' . e($label) . '</td>
                    <td style="padding:10px 0;border-top:1px solid #262626;color:#f5f5f2;font-size:14px;vertical-align:top;">' . e($value) . '</td>
                </tr>';
        }
        $detailsHtml = '
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:24px;">
                ' . $rows . '
            </table>';
    }

    $ctaHtml = '';
    if ($cta !== null) {
        $ctaHtml = '
            <table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:28px;">
                <tr>
                    <td style="background-color:#d7ff00;">
                        <a href="' . e($cta['url']) . '" style="display:inline-block;padding:14px 28px;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#000000;text-decoration:none;">' . e($cta['label']) . '</a>
                    </td>
                </tr>
            </table>';
    }

    return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . e($heading) . '</title>
</head>
<body style="margin:0;padding:0;background-color:#050505;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#050505;">
<tr>
<td align="center" style="padding:32px 16px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background-color:#111111;border-top:4px solid #d7ff00;">
<tr>
<td style="padding:32px 32px 0 32px;">
<div style="font-family:Arial Black,Arial,Helvetica,sans-serif;font-size:20px;font-weight:900;letter-spacing:1px;text-transform:uppercase;color:#ffffff;">
SHUTTER <span style="color:#d7ff00;">&amp;</span> SPEED
</div>
</td>
</tr>
<tr>
<td style="padding:28px 32px 0 32px;">
<div style="font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#d7ff00;">
' . e($eyebrow) . '
</div>
<div style="font-family:Arial,Helvetica,sans-serif;font-size:24px;font-weight:700;color:#ffffff;margin-top:8px;">
' . e($heading) . '
</div>
</td>
</tr>
<tr>
<td style="padding:16px 32px 0 32px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#cccccc;">
' . $bodyHtml . '
</td>
</tr>
<tr>
<td style="padding:0 32px;">
' . $detailsHtml . $ctaHtml . '
</td>
</tr>
<tr>
<td style="padding:32px;">
<div style="border-top:1px solid #262626;padding-top:20px;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#555555;">
Shutter &amp; Speed Photography — Gaurav Kant · shutterandspeed.co.nz · 022 124 0224
</div>
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>';
}

/**
 * Sends an HTML email with the right headers. $headers is a list of
 * additional header lines (From, Reply-To, ...) — Content-Type and
 * MIME-Version are added automatically.
 *
 * @param string[] $headers
 */
function sendHtmlEmail(string $to, string $subject, string $html, array $headers): bool
{
    $allHeaders = array_merge(
        ['MIME-Version: 1.0', 'Content-Type: text/html; charset=UTF-8'],
        $headers
    );

    return mail($to, $subject, $html, implode("\r\n", $allHeaders));
}

/**
 * Base64url encode/decode — used for the signed confirm-link payload
 * so it's safe to put directly in a URL query string.
 */
function base64UrlEncode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64UrlDecode(string $data): string
{
    $padded = str_pad($data, strlen($data) % 4 === 0 ? strlen($data) : strlen($data) + (4 - strlen($data) % 4), '=');
    return (string) base64_decode(strtr($padded, '-_', '+/'), true);
}

/**
 * Signs a booking-details payload for the "Confirm Payment Received"
 * link. Returns the data/sig pair to put in the URL query string.
 *
 * @param array<string,string> $fields
 * @return array{data:string,sig:string}
 */
function signBookingPayload(array $fields, string $secret): array
{
    $data = base64UrlEncode((string) json_encode($fields));
    $sig = base64UrlEncode(hash_hmac('sha256', $data, $secret, true));

    return ['data' => $data, 'sig' => $sig];
}

/**
 * Verifies a data/sig pair and returns the decoded fields, or null
 * if the signature doesn't match (tampered or forged link).
 *
 * @return array<string,string>|null
 */
function verifyBookingPayload(string $data, string $sig, string $secret): ?array
{
    $expected = base64UrlEncode(hash_hmac('sha256', $data, $secret, true));

    if (!hash_equals($expected, $sig)) {
        return null;
    }

    $decoded = json_decode(base64UrlDecode($data), true);

    return is_array($decoded) ? $decoded : null;
}
