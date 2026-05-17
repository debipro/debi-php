<?php

/**
 * Receives a real webhook from Debi and verifies its signature with
 * {@see \Debi\Webhook}.
 *
 * Run as PHP's built-in server router:
 *
 *   DEBI_WEBHOOK_SECRET=whsec_xxx php -S 0.0.0.0:8765 examples/webhook_listener.php
 *
 * Then expose port 8765 to the internet so Debi can reach you. Easiest options:
 *
 *   ngrok http 8765
 *   cloudflared tunnel --url http://localhost:8765
 *
 * Configure the resulting public URL as a webhook endpoint in the Debi
 * dashboard, copy the signing secret it generates, restart this listener with
 * that secret, and trigger an event (e.g. create a customer in the dashboard).
 *
 * The listener will print every incoming request and tell you whether the
 * signature verified.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Debi\Exception\SignatureVerificationException;
use Debi\Webhook;

// Diagnostic output goes through error_log so it shows up in the terminal
// alongside PHP's built-in server access log, and never leaks into the HTTP
// response body. We deliberately do NOT use STDERR here because the constant
// is only defined when PHP runs as a CLI script — under `php -S`, each request
// is handled in a worker context where STDERR is undefined and writing to it
// would 500 the response.
$log = static function (string $msg): void {
    error_log($msg);
};

$secret = getenv('DEBI_WEBHOOK_SECRET') ?: '';
if ($secret === '') {
    http_response_code(500);
    echo "Server misconfigured: DEBI_WEBHOOK_SECRET is not set.\n";
    $log('FATAL: DEBI_WEBHOOK_SECRET is not set in the server environment.');
    return;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path   = $_SERVER['REQUEST_URI'] ?? '/';
$sig    = $_SERVER['HTTP_DEBI_SIGNATURE'] ?? '';
$body   = file_get_contents('php://input') ?: '';

$log(str_repeat('─', 60));
$log(sprintf('%s %s   @ %s', $method, $path, date('c')));
$log('Debi-Signature: ' . ($sig === '' ? '(missing)' : $sig));
$log('Body bytes:     ' . strlen($body));
if (strlen($body) < 1024) {
    $log('Body:           ' . $body);
} else {
    $log('Body (preview): ' . substr($body, 0, 1024) . '...');
}

if ($method !== 'POST') {
    http_response_code(200);
    echo "Webhook listener is alive. POST a signed event to /.\n";
    $log('Non-POST request ignored.');
    return;
}

if ($sig === '') {
    http_response_code(400);
    echo "Missing Debi-Signature header.\n";
    $log('REJECTED: Debi-Signature header was not present.');
    return;
}

try {
    $event = Webhook::constructEvent($body, $sig, $secret);
    http_response_code(200);
    echo "ok\n";
    $log('VERIFIED OK');
    $log('  event.id:   ' . ($event->id ?? '(unknown)'));
    $log('  event.type: ' . ($event->type ?? '(unknown)'));
} catch (SignatureVerificationException $e) {
    http_response_code(400);
    echo "Signature rejected.\n";
    $log('REJECTED: ' . $e->getMessage());
    // Intentionally do NOT log the SDK-computed expected HMAC here. While it
    // is not the raw signing secret, it is signing material an attacker could
    // use against replayed (timestamp, body) pairs. If you need to debug a
    // mismatch locally, do so behind a temporary feature flag and never in a
    // shared / production log destination.
}
