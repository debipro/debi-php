# Debi PHP Library

The official PHP library for the [Debi API](https://debi.pro).

## Requirements

- PHP 8.1 or higher
- A [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP client (auto-discovered; Guzzle, Symfony HttpClient, etc.)

## Installation

```bash
composer require debi/debi-php
```

If your project does not already provide a PSR-18 client and PSR-17 factories, install one — for example:

```bash
composer require guzzlehttp/guzzle
```

## Quickstart

```php
require_once __DIR__ . '/vendor/autoload.php';

$debi = new \Debi\DebiClient('sk_live_...');

$customer = $debi->customers->create([
    'email' => 'ana@example.com',
    'name'  => 'Ana Pérez',
]);

$payment = $debi->payments->create([
    'amount'      => 50000,
    'currency'    => 'ARS',
    'customer_id' => $customer->id,
], ['idempotency_key' => 'order-1234']);
```

### Sandbox

```php
$debi = new \Debi\DebiClient([
    'api_key'  => 'sk_test_...',
    'api_base' => \Debi\DebiClient::DEFAULT_SANDBOX_BASE,
]);
```

### Pagination

```php
foreach ($debi->customers->all(['limit' => 100])->autoPagingIterator() as $customer) {
    // ...
}
```

### Webhook verification

Always pass the **raw, unmodified** request body — any middleware that
re-encodes JSON or trims whitespace will break verification.

```php
try {
    $event = \Debi\Webhook::constructEvent(
        payload:   file_get_contents('php://input'),
        sigHeader: $_SERVER['HTTP_DEBI_SIGNATURE'],
        secret:    getenv('DEBI_WEBHOOK_SECRET'),
    );
} catch (\Debi\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    exit;
}
```

### Error handling

```php
try {
    $debi->payments->create([...]);
} catch (\Debi\Exception\InvalidRequestException $e) {
    // 400 / 422 — surface $e->validationErrors to the user
} catch (\Debi\Exception\AuthenticationException $e) {
    // 401 — wrong key
} catch (\Debi\Exception\RateLimitException $e) {
    // 429 — back off and retry
} catch (\Debi\Exception\ApiErrorException $e) {
    // Any other 4xx/5xx
} catch (\Debi\Exception\ExceptionInterface $e) {
    // Catch-all for anything the SDK throws (transport, signature, etc.)
}
```

## Supported resources

`$debi->customers`, `$debi->payments`, `$debi->subscriptions`, `$debi->mandates`,
`$debi->paymentMethods`, `$debi->refunds`, `$debi->sessions`, `$debi->links`,
`$debi->events`, `$debi->exports`, `$debi->imports`, `$debi->gateways`,
`$debi->webhookEndpoints`, `$debi->billingPortalSessions`,
`$debi->billingPortalConfigurations`.

Each property returns a service exposing the standard set of CRUD-style methods
(`all`, `retrieve`, `create`, `update`, `search`, plus resource-specific
actions). Responses are hydrated into typed `\Debi\Resource\*` objects.

The wire surface is verified against the [Debi OpenAPI specification](https://debi.pro/docs/api):
endpoint paths, HTTP methods, parameter names, and `object` discriminators all
match the published spec.

### Billing portal

The billing portal is a two-resource flow: **Configurations** describe what the
portal looks and behaves like, **Sessions** are short-lived redirect URLs you
generate for each customer.

```php
// One-time setup: create a configuration (or reuse the account's default).
$config = $debi->billingPortalConfigurations->create([
    'features' => [
        'customer_update'       => false,
        'invoice_history'       => true,
        'payment_method_update' => true,
        'subscription_cancel'   => true,
    ],
    'login_page' => ['enabled' => true],
    'business_profile' => ['headline' => 'Manage your subscription'],
]);

// Per-request: create a session and redirect the customer.
$session = $debi->billingPortalSessions->create([
    'customer_id' => $customer->id,
    'return_url'  => 'https://app.example/account',
    // optional — falls back to the account default when omitted
    'billing_portal_configuration_id' => $config->id,
]);

header('Location: ' . $session->url);
```

The session's `url` is short-lived and single-use; create a new session for
every redirect rather than caching it. There is intentionally **no**
`billingPortalSessions->retrieve()` — the API does not expose one.

> **Heads-up:** The billing portal feature must be enabled on the account.
> A `404` on any of these endpoints means you need to contact Debi support to
> enable it for your tenant.

### Payment methods: attach / detach

```php
// Attach an existing payment method to a customer.
$debi->paymentMethods->attach($paymentMethod->id, $customer->id);

// Detach it later.
$debi->paymentMethods->detach($paymentMethod->id);
```

Both endpoints return `204 No Content`; the SDK returns an empty
`\Debi\DebiObject` for symmetry with the rest of the surface.

### BETA: payment ↔ bank-transaction reconciliation

The payment service exposes three BETA endpoints for reconciling a payment
against bank-transaction data. **These are subject to breaking change without
notice.**

```php
$tx          = $debi->payments->transaction($payment->id);
$candidates  = $debi->payments->transactionMatchCandidates($payment->id);
$debi->payments->transactionMatch($payment->id, ['remote_transaction_id' => 'RT123']);
```

## Examples

The `examples/` directory contains runnable scripts:

| File | What it does |
| --- | --- |
| `quickstart.php` | Offline smoke test with a stubbed HTTP client (no network) |
| `sandbox.php` | Live sandbox runner that exercises the full surface against `https://api.debi-test.pro` (needs `DEBI_API_KEY=sk_test_...`) |
| `webhook_listener.php` | Minimal listener showing webhook signature verification |
| `webhook_e2e.php` | End-to-end webhook round trip |

## Versioning

This library uses [Semantic Versioning](https://semver.org/). The API version pinned by each release
is sent via the `Debi-Version` header and is updated only on major SDK releases.

See [`CHANGELOG.md`](CHANGELOG.md) for release notes.

## Security

If you discover a security issue, please report it privately following
[`SECURITY.md`](SECURITY.md). Do not open a public GitHub issue.

## License

MIT — see [LICENSE](LICENSE).
