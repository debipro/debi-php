<?php

/**
 * End-to-end webhook test script.
 *
 * This script exercises the *complete* signature-verification surface of the
 * SDK without needing a public tunnel:
 *
 *   1. Replays the same HMAC signing algorithm Debi uses server-side to
 *      produce a signed payload, then verifies it with {@see \Debi\Webhook}.
 *   2. Drives every documented rejection path (bad secret, tampered body,
 *      expired timestamp, key rotation, malformed header) so each branch of
 *      the verifier is observed working.
 *   3. Optionally registers a real webhook endpoint on a Debi instance, prints
 *      the signing secret, and gives the user a turn-key command to receive
 *      and verify a live event via `examples/webhook_listener.php`.
 *
 * Usage:
 *
 *   # Offline checks only (always works, no network).
 *   php examples/webhook_e2e.php
 *
 *   # Offline + live setup against the sandbox (creates a webhook pointing
 *   # at your public tunnel URL, prints the signing secret, and shows the
 *   # listener command to run).
 *   DEBI_API_KEY=sk_test_... \
 *     php examples/webhook_e2e.php --setup-live --public-url=https://your-tunnel.example.com/
 *
 *   # Override the API base (e.g. production or a custom environment).
 *   DEBI_API_KEY=sk_test_... DEBI_API_BASE=https://api.debi.pro \
 *     php examples/webhook_e2e.php --setup-live --public-url=https://your-tunnel.example.com/
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Debi\DebiClient;
use Debi\Exception\SignatureVerificationException;
use Debi\Webhook;

$opts = parseArgs($argv);

$cyan = "\033[1;36m";
$dim = "\033[2m";
$ok = "\033[1;32m";
$bad = "\033[1;31m";
$reset = "\033[0m";

$banner = static function (string $title) use ($cyan, $reset): void {
    echo "\n{$cyan}── {$title} ─────────────────────────────────{$reset}\n";
};
$pass = static function (string $msg) use ($ok, $reset): void {
    echo "  {$ok}PASS{$reset}  {$msg}\n";
};
$fail = static function (string $msg) use ($bad, $reset): void {
    echo "  {$bad}FAIL{$reset}  {$msg}\n";
    exit(1);
};

/**
 * Mirror of Debi's server-side signing logic. Building it here means we
 * exercise the SDK against bytes shaped *exactly* like the server produces,
 * instead of a SDK-shaped facsimile.
 *
 * @param list<string> $secrets all secrets the endpoint has (for rotation)
 * @return array{0: string, 1: string} [signature_header, raw_body]
 */
$sign = static function (array $event, array $secrets, ?int $timestamp = null): array {
    $body = json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    $ts = $timestamp ?? time();
    $hashData = sprintf('%d.%s', $ts, $body);

    $parts = [];
    foreach ($secrets as $secret) {
        $hash = hash_hmac('sha256', $hashData, $secret);
        $parts[] = "t={$ts},v1={$hash}";
    }
    return [implode(', ', $parts), $body];
};

$banner('1. Offline round-trip with the canonical algorithm');

$secret = 'whsec_' . bin2hex(random_bytes(16));
$event = [
    'id' => 'EV1rRDBDOEJM',
    'object' => 'event',
    'type' => 'customer.created',
    'livemode' => false,
    'resource' => 'customer',
    'resource_id' => 'CSjRZ5JqjAw0',
    'created_at' => date('c'),
    'data' => ['object' => ['id' => 'CSjRZ5JqjAw0', 'object' => 'customer']],
];
[$sig, $body] = $sign($event, [$secret]);
echo "  {$dim}secret:  {$secret}{$reset}\n";
echo "  {$dim}sig:     {$sig}{$reset}\n";
echo "  {$dim}body:    " . substr($body, 0, 80) . "...{$reset}\n";

try {
    $parsed = Webhook::constructEvent($body, $sig, $secret);
    $pass("constructEvent returned Event id={$parsed->id} type={$parsed->type}");
} catch (SignatureVerificationException $e) {
    $fail("expected success, got SignatureVerificationException: {$e->getMessage()}");
}

$banner('2. Rejection paths');

try {
    Webhook::constructEvent($body, $sig, 'whsec_wrong_secret');
    $fail('expected exception for wrong secret');
} catch (SignatureVerificationException) {
    $pass('wrong secret is rejected');
}

try {
    Webhook::constructEvent($body . ' ', $sig, $secret);
    $fail('expected exception for tampered body');
} catch (SignatureVerificationException) {
    $pass('body mutation (added whitespace) is rejected');
}

[$staleSig, $staleBody] = $sign($event, [$secret], time() - 3600);
try {
    Webhook::constructEvent($staleBody, $staleSig, $secret);
    $fail('expected exception for stale timestamp');
} catch (SignatureVerificationException) {
    $pass('1-hour-old timestamp is rejected by the 300s tolerance');
}

try {
    Webhook::constructEvent($body, "t=" . time() . ",v1=deadbeef", $secret);
    $fail('expected exception for fabricated v1');
} catch (SignatureVerificationException) {
    $pass('fabricated v1 value is rejected');
}

try {
    Webhook::constructEvent($body, "v1=" . hash_hmac('sha256', $body, $secret), $secret);
    $fail('expected exception for missing t=');
} catch (SignatureVerificationException) {
    $pass('header without t= is rejected');
}

try {
    Webhook::constructEvent($body, "t=" . time(), $secret);
    $fail('expected exception for missing v1=');
} catch (SignatureVerificationException) {
    $pass('header without v1= is rejected');
}

try {
    Webhook::constructEvent($body, '', $secret);
    $fail('expected exception for empty header');
} catch (SignatureVerificationException) {
    $pass('empty header is rejected');
}

try {
    Webhook::constructEvent($body, $sig, '');
    $fail('expected exception for empty secret');
} catch (SignatureVerificationException) {
    $pass('empty secret is rejected');
}

$banner('3. Secret rotation: a delivery signed with both old + new is accepted by either');

$oldSecret = 'whsec_old_' . bin2hex(random_bytes(8));
$newSecret = 'whsec_new_' . bin2hex(random_bytes(8));
[$rotatedSig, $rotatedBody] = $sign($event, [$oldSecret, $newSecret]);

try {
    Webhook::constructEvent($rotatedBody, $rotatedSig, $oldSecret);
    $pass('endpoint holding only the OLD secret can still verify');
} catch (SignatureVerificationException $e) {
    $fail("rotation old-side failed: {$e->getMessage()}");
}
try {
    Webhook::constructEvent($rotatedBody, $rotatedSig, $newSecret);
    $pass('endpoint holding only the NEW secret can also verify');
} catch (SignatureVerificationException $e) {
    $fail("rotation new-side failed: {$e->getMessage()}");
}

$banner('4. Header whitespace tolerance');

[$cleanSig, $cleanBody] = $sign($event, [$secret]);
$paddedSig = preg_replace('/,\s*/', ' ,   ', $cleanSig) ?? $cleanSig;
try {
    Webhook::constructEvent($cleanBody, $paddedSig, $secret);
    $pass('extra whitespace around `,` separators is tolerated');
} catch (SignatureVerificationException $e) {
    $fail("whitespace tolerance failed: {$e->getMessage()}");
}

if (!$opts['setupLive']) {
    echo "\n{$cyan}── DONE  ─────────────────────────────────{$reset}\n";
    echo "  All offline signature checks passed.\n";
    echo "  To set up a live receiver, re-run with `--setup-live` and a DEBI_API_KEY.\n\n";
    return;
}

$banner('5. Live setup: create a real endpoint on the Debi backend');

$apiKey = getenv('DEBI_API_KEY') ?: '';
if ($apiKey === '') {
    $fail('--setup-live requires DEBI_API_KEY in the environment.');
}
$apiBase = getenv('DEBI_API_BASE') ?: DebiClient::DEFAULT_SANDBOX_BASE;
$publicUrl = $opts['publicUrl'] ?? ('http://localhost:8765/' . bin2hex(random_bytes(4)));

$client = new DebiClient($apiKey, ['api_base' => $apiBase]);

echo "  api_base:   {$apiBase}\n";
echo "  callback:   {$publicUrl}\n";

$endpoint = $client->webhookEndpoints->create([
    'url' => $publicUrl,
    'enabled_events' => ['*'],
]);

$pass("created endpoint id={$endpoint->id}");
echo "  {$dim}secret:     {$endpoint->secret}{$reset}\n";

echo "\n  Next steps:\n";
echo "    1) In one terminal, start the listener:\n";
echo "       {$ok}DEBI_WEBHOOK_SECRET={$endpoint->secret} \\\n";
echo "         php -S 0.0.0.0:8765 examples/webhook_listener.php{$reset}\n";
echo "    2) In another terminal, trigger an event so Debi delivers something:\n";
echo "       {$ok}DEBI_API_KEY=$apiKey \\\n";
echo "         DEBI_API_BASE={$apiBase} \\\n";
echo "         php examples/quickstart.php{$reset}\n";
echo "    3) Watch the listener: it should print `VERIFIED OK` for each event.\n";
echo "    4) Clean up when done:\n";
echo "       {$ok}curl -X DELETE {$apiBase}/v1/webhooks/{$endpoint->id} \\\n";
echo "         -H 'Authorization: Bearer {$apiKey}' -H 'Accept: application/json'{$reset}\n";

/**
 * @return array{setupLive: bool, publicUrl: ?string}
 */
function parseArgs(array $argv): array
{
    $setupLive = in_array('--setup-live', $argv, true);
    $publicUrl = null;
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--public-url=')) {
            $publicUrl = substr($arg, strlen('--public-url='));
        }
    }
    return ['setupLive' => $setupLive, 'publicUrl' => $publicUrl];
}
