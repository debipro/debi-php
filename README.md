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

## Versioning

This library uses [Semantic Versioning](https://semver.org/). The API version pinned by each release
is sent via the `Debi-Version` header and is updated only on major SDK releases.

## License

MIT — see [LICENSE](LICENSE).
