<?php

/**
 * Confirms the API's validation-error response shape matches what
 * {@see \Debi\Exception\ApiErrorException::fromResponse()} expects.
 *
 * Usage:
 *
 *   DEBI_API_KEY=sk_test_xxx php examples/test_validation.php
 *   DEBI_API_KEY=sk_test_xxx DEBI_API_BASE=https://api.debi.pro \
 *     php examples/test_validation.php
 *
 * What it does:
 *   - Sends a customer-create request guaranteed to fail validation.
 *   - Prints the raw HTTP body the server returned.
 *   - Prints how the SDK parsed it (`message` + `validationErrors`).
 *   - Tells you whether the SDK's parser handled it correctly.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Debi\DebiClient;
use Debi\Exception\ApiErrorException;
use Debi\Exception\InvalidRequestException;
use Debi\HttpClient\ClientInterface;
use Debi\HttpClient\DefaultClient;
use Debi\HttpClient\Response;

$apiKey = getenv('DEBI_API_KEY') ?: null;
$apiBase = getenv('DEBI_API_BASE') ?: DebiClient::DEFAULT_SANDBOX_BASE;

if (!$apiKey) {
    fwrite(STDERR, "Set DEBI_API_KEY (and optionally DEBI_API_BASE) and rerun.\n");
    exit(1);
}

echo "Target: {$apiBase}\n\n";

// Wrap the default client so we can also capture the raw response body
// regardless of the SDK's parsing, for side-by-side inspection.
$rawResponse = null;
$http = new class(new DefaultClient(), $rawResponse) implements ClientInterface {
    public function __construct(
        private ClientInterface $inner,
        public ?Response &$last,
    ) {}
    public function send(string $method, string $url, array $headers, ?string $body): Response
    {
        $response = $this->inner->send($method, $url, $headers, $body);
        $this->last = $response;
        return $response;
    }
};

$client = new DebiClient([
    'api_key' => $apiKey,
    'api_base' => $apiBase,
    'http_client' => $http,
]);

$cases = [
    'empty body' => [],
    'invalid email' => ['email' => 'not-an-email'],
    'invalid mobile' => ['mobile_number' => '?????'],
];

foreach ($cases as $label => $payload) {
    echo "─── case: {$label} ─────────────────────────\n";
    echo "  POST body: " . json_encode($payload) . "\n";
    try {
        $client->customers->create($payload);
        echo "  unexpected: API accepted invalid payload.\n\n";
        continue;
    } catch (InvalidRequestException $e) {
        echo "  status:     {$e->httpStatus}\n";
        echo "  exception:  " . get_class($e) . "\n";
        echo "  raw body:   {$e->httpBody}\n";
        echo "  parsed message:    \"{$e->getMessage()}\"\n";
        echo "  parsed errorCode:  " . ($e->errorCode ?? '(none)') . "\n";
        echo "  parsed validationErrors:\n";
        if ($e->validationErrors === []) {
            echo "    (empty — possible parser mismatch)\n";
        } else {
            foreach ($e->validationErrors as $field => $msg) {
                echo "    {$field}: {$msg}\n";
            }
        }
        echo "\n";
    } catch (ApiErrorException $e) {
        echo "  got " . get_class($e) . " ({$e->httpStatus}) instead of InvalidRequestException\n";
        echo "  raw body: {$e->httpBody}\n\n";
    } catch (\Throwable $e) {
        echo "  unexpected " . get_class($e) . ": {$e->getMessage()}\n\n";
    }
}

echo "─── done ─────────────────────────\n";
echo "If `validationErrors` is populated for all cases, the SDK's parser is correct.\n";
echo "If any case shows `(empty — possible parser mismatch)`, share the raw body above\n";
echo "and we'll adjust ApiErrorException::fromResponse().\n";
