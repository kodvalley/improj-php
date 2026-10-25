<?php

declare(strict_types=1);

/*
 * A plain PHP webhook receiver. Point an IMPROJ webhook endpoint at this
 * script (https only) and set IMPROJ_WEBHOOK_SECRET to the endpoint's whsec_ secret.
 *
 * 1. Read the raw body from php://input (never $_POST, never a re-encoded array).
 * 2. Verify the signature and the 300 second window.
 * 3. De-duplicate on the delivery id: retries deliver the same event again.
 * 4. Answer 204 quickly; do the work later (fetch the object through the API).
 */

require __DIR__ . '/../vendor/autoload.php';

use Kodvalley\Improj\Webhook\SignatureVerificationException;
use Kodvalley\Improj\Webhook\WebhookVerifier;

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

$secret = getenv('IMPROJ_WEBHOOK_SECRET');
if (!is_string($secret) || $secret === '') {
    http_response_code(500);
    exit;
}

// Deliveries are small (ids only); refuse anything large before reading it.
$length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($length <= 0 || $length > 65536) {
    http_response_code(413);
    exit;
}
$payload = (string) file_get_contents('php://input', false, null, 0, 65536);

$header = $_SERVER['HTTP_IMPROJ_SIGNATURE'] ?? null;
try {
    $event = (new WebhookVerifier($secret))->constructEvent($payload, is_string($header) ? $header : null);
} catch (SignatureVerificationException $e) {
    // Do not explain why to the caller; log the reason for yourself.
    error_log('IMPROJ webhook refused: ' . $e->getReason());
    http_response_code(400);
    exit;
}

// De-duplicate. A file per delivery keeps the example dependency-free; use your database in production.
$seenDir = sys_get_temp_dir() . '/improj-webhooks';
if (!is_dir($seenDir) && !mkdir($seenDir, 0700, true) && !is_dir($seenDir)) {
    http_response_code(500);
    exit;
}
$marker = $seenDir . '/' . $event->deliveryId . '.seen';
$handle = @fopen($marker, 'x'); // fails if the file exists: already handled
if ($handle === false) {
    http_response_code(204);
    exit;
}
fclose($handle);

// Queue the work instead of doing it here; the API waits at most 10 seconds.
error_log(sprintf('IMPROJ %s for %s %s in project %s', $event->type, $event->data->object, (string) $event->data->id, (string) $event->data->projectId));

http_response_code(204);
