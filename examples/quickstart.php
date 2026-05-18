<?php

/**
 * Quickstart smoke test for the Debi PHP SDK.
 *
 * Usage:
 *
 *   # Offline mode — no network, uses a stubbed HTTP client. Always works.
 *   php examples/quickstart.php
 *
 *   # Live sandbox mode — hits https://api.debi-test.pro
 *   DEBI_API_KEY=sk_test_xxx php examples/quickstart.php
 *
 *   # Custom environment (e.g. production or a self-hosted base URL)
 *   DEBI_API_KEY=sk_test_xxx DEBI_API_BASE=https://api.debi.pro \
 *     php examples/quickstart.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Debi\DebiClient;
use Debi\Exception\ApiErrorException;
use Debi\Exception\ExceptionInterface;
use Debi\Exception\InvalidRequestException;
use Debi\HttpClient\ClientInterface;
use Debi\HttpClient\Response;
use Debi\Webhook;

$apiKey = getenv('DEBI_API_KEY') ?: null;
$apiBase = getenv('DEBI_API_BASE') ?: DebiClient::DEFAULT_SANDBOX_BASE;

if ($apiKey === null) {
    section('MODE: offline (stubbed HTTP)');
    $client = makeOfflineClient();
} else {
    section("MODE: live  ({$apiBase})");
    $client = new DebiClient([
        'api_key' => $apiKey,
        'api_base' => $apiBase,
    ]);
}

// ---------------------------------------------------------------------------
section('1. Create a customer with an Idempotency-Key');
try {
    $customer = $client->customers->create([
        'email' => 'ana+' . bin2hex(random_bytes(3)) . '@example.com',
        'name'  => 'Ana Pérez',
    ], ['idempotency_key' => 'demo-' . bin2hex(random_bytes(4))]);
    out("created  id={$customer->id}  email={$customer->email}");
} catch (InvalidRequestException $e) {
    out('Validation failed: ' . $e->getMessage());
    foreach ($e->validationErrors as $field => $msg) {
        out("  - {$field}: {$msg}");
    }
    exit(1);
} catch (ExceptionInterface $e) {
    fail($e);
}

// ---------------------------------------------------------------------------
section('2. Retrieve it back');
try {
    $fetched = $client->customers->retrieve($customer->id);
    out("retrieved id={$fetched->id}  ({$fetched->object})");
} catch (ExceptionInterface $e) {
    fail($e);
}

// ---------------------------------------------------------------------------
section('3. List customers (single page)');
try {
    $list = $client->customers->all(['limit' => 5]);
    $count = 0;
    foreach ($list as $c) {
        out("  - {$c->id}  {$c->email}");
        $count++;
    }
    out("total on this page: {$count}");
} catch (ExceptionInterface $e) {
    fail($e);
}

// ---------------------------------------------------------------------------
section('4. Webhook signature verification (round-trip)');
$secret  = 'whsec_demo_secret';
$payload = json_encode([
    'id'      => 'evt_demo',
    'object'  => 'event',
    'type'    => 'customer.created',
    'data'    => ['object' => ['id' => $customer->id, 'object' => 'customer']],
], JSON_THROW_ON_ERROR);
$ts      = time();
$sig     = hash_hmac('sha256', $ts . '.' . $payload, $secret);
$header  = "t={$ts},v1={$sig}";

try {
    $event = Webhook::constructEvent($payload, $header, $secret);
    out("verified  type={$event->type}  id={$event->id}");
} catch (ExceptionInterface $e) {
    fail($e);
}

// ---------------------------------------------------------------------------
section('5. Error handling: deliberately bad input');
try {
    $client->customers->retrieve('CS0000notFound0');
    out('(unexpected: no error thrown)');
} catch (ApiErrorException $e) {
    out('caught ' . $e::class . "  status={$e->httpStatus}  msg=\"{$e->getMessage()}\"");
} catch (ExceptionInterface $e) {
    out('caught ' . $e::class . "  msg=\"{$e->getMessage()}\"");
}

section('DONE');

// =============================================================================
// helpers
// =============================================================================

function section(string $title): void
{
    echo "\n\033[1;36m── {$title} ─────────────────────────────────\033[0m\n";
}

function out(string $msg): void
{
    echo "  {$msg}\n";
}

function fail(\Throwable $e): void
{
    echo "\n\033[1;31mFAILED:\033[0m " . get_class($e) . ': ' . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Build a DebiClient backed by a scripted in-memory HTTP client. Lets the
 * quickstart prove the entire SDK wiring works without any network.
 */
function makeOfflineClient(): DebiClient
{
    $http = new class implements ClientInterface {
        /** @var list<Response> */
        private array $script;

        public function __construct()
        {
            $this->script = [
                new Response(201, json_encode([
                    'data' => [
                        'id' => 'CSjRZ5JqjAw0', 'object' => 'customer', 'livemode' => false,
                        'email' => 'ana@example.com', 'name' => 'Ana Pérez',
                    ],
                ], JSON_THROW_ON_ERROR), []),
                new Response(200, json_encode([
                    'data' => [
                        'id' => 'CSjRZ5JqjAw0', 'object' => 'customer', 'livemode' => false,
                        'email' => 'ana@example.com',
                    ],
                ], JSON_THROW_ON_ERROR), []),
                new Response(200, json_encode([
                    'data' => [
                        ['id' => 'CSjRZ5JqjAw0', 'object' => 'customer', 'email' => 'ana@example.com'],
                        ['id' => 'CSkywYrxQYDR', 'object' => 'customer', 'email' => 'beto@example.com'],
                    ],
                    // Pagination envelope matches openapi/components/schemas/{Links,Meta}.yaml.
                    'links' => ['first' => null, 'last' => null, 'next' => null, 'prev' => null],
                    'meta' => ['per_page' => 5, 'total' => 2, 'path' => 'https://api.debi.pro/v1/customers', 'next_cursor' => null],
                ], JSON_THROW_ON_ERROR), []),
                new Response(404, json_encode([
                    'message' => 'Record not found.',
                ], JSON_THROW_ON_ERROR), []),
            ];
        }

        public function send(string $method, string $url, array $headers, ?string $body): Response
        {
            echo "  \033[2m→ {$method} {$url}\033[0m\n";
            if (isset($headers['Idempotency-Key'])) {
                echo "  \033[2m  Idempotency-Key: {$headers['Idempotency-Key']}\033[0m\n";
            }
            $next = array_shift($this->script);
            if ($next === null) {
                throw new \LogicException("Stub ran out of scripted responses for {$method} {$url}");
            }
            return $next;
        }
    };

    return new DebiClient([
        'api_key' => 'sk_test_offline',
        'http_client' => $http,
    ]);
}
