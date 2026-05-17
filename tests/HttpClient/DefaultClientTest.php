<?php

declare(strict_types=1);

namespace Debi\Tests\HttpClient;

use Debi\Exception\TransportException;
use Debi\HttpClient\DefaultClient;
use Debi\Tests\Support\FakePsr18Client;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response as Psr7Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;

/**
 * Direct tests for the SDK's retry policy.
 *
 * Production behavior is the same as before: GET/HEAD/PUT/DELETE retry on
 * transport failures and on 429/5xx; POST retries only when carrying an
 * `Idempotency-Key`; server-supplied `Retry-After` is honored when present.
 * These tests pin every branch of that policy so a future refactor cannot
 * silently re-introduce a double-charge or starve a 429 backoff.
 */
final class DefaultClientTest extends TestCase
{
    private FakePsr18Client $http;
    /** @var list<int> microseconds passed to each retry sleep */
    private array $slept = [];

    protected function setUp(): void
    {
        $this->http = new FakePsr18Client();
        $this->slept = [];
    }

    private function client(array $overrides = []): DefaultClient
    {
        $factory = new HttpFactory();
        return new DefaultClient([
            'http_client' => $this->http,
            'request_factory' => $factory,
            'stream_factory' => $factory,
            // Default backoffs would make some assertions wallclock-bound;
            // tests configure their own and observe via the sleeper seam.
            'initial_backoff_ms' => 1,
            'max_backoff_ms' => 1,
            'sleeper' => function (int $us): void {
                $this->slept[] = $us;
            },
        ] + $overrides);
    }

    private function transportError(string $message = 'simulated transport failure'): ClientExceptionInterface
    {
        return new class($message) extends \RuntimeException implements ClientExceptionInterface {};
    }

    // -----------------------------------------------------------------------
    //  Idempotency gating — the most safety-critical guarantee in the SDK.
    // -----------------------------------------------------------------------

    #[Test]
    public function post_without_idempotency_key_is_never_retried_on_transport_failure(): void
    {
        // This is the "do not double-charge" guarantee. If this test ever
        // turns red the SDK has lost the right to retry POSTs silently.
        $this->http->queue($this->transportError());

        try {
            $this->client()->send('POST', 'https://api.example.test/v1/payments', [], '{}');
            self::fail('Expected TransportException to be rethrown.');
        } catch (TransportException) {
            // expected
        }

        $this->assertSame(1, $this->http->attemptCount(), 'POST without Idempotency-Key must not retry.');
        $this->assertSame([], $this->slept, 'No retry sleep should be issued.');
    }

    #[Test]
    public function post_with_idempotency_key_is_retried_on_transport_failure(): void
    {
        $this->http->queue($this->transportError());
        $this->http->queue($this->transportError());
        $this->http->queue(new Psr7Response(201, [], '{"object":"payment","id":"PY1"}'));

        $response = $this->client()->send(
            'POST',
            'https://api.example.test/v1/payments',
            ['Idempotency-Key' => 'order-1'],
            '{}',
        );

        $this->assertSame(201, $response->status);
        $this->assertSame(3, $this->http->attemptCount());
        $this->assertCount(2, $this->slept, 'Two retries should have slept once each.');
    }

    #[Test]
    public function post_with_lowercase_idempotency_key_is_still_recognized(): void
    {
        // Header names are case-insensitive (RFC 7230). The SDK must honor
        // that on its own input or callers will be silently misclassified.
        $this->http->queue($this->transportError());
        $this->http->queue(new Psr7Response(201, [], '{}'));

        $this->client()->send(
            'POST',
            'https://api.example.test/v1/payments',
            ['idempotency-key' => 'order-1'],
            '{}',
        );

        $this->assertSame(2, $this->http->attemptCount());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function idempotentMethodsProvider(): iterable
    {
        yield 'GET'    => ['GET'];
        yield 'HEAD'   => ['HEAD'];
        yield 'PUT'    => ['PUT'];
        yield 'DELETE' => ['DELETE'];
    }

    #[Test]
    #[DataProvider('idempotentMethodsProvider')]
    public function idempotent_methods_are_retried_on_transport_failure(string $method): void
    {
        $this->http->queue($this->transportError());
        $this->http->queue(new Psr7Response(200, [], '{}'));

        $response = $this->client()->send($method, 'https://api.example.test/v1/x', [], null);

        $this->assertSame(200, $response->status);
        $this->assertSame(2, $this->http->attemptCount());
    }

    // -----------------------------------------------------------------------
    //  Retry-on-status policy.
    // -----------------------------------------------------------------------

    /**
     * @return iterable<string, array{int, bool}>
     */
    public static function statusRetryProvider(): iterable
    {
        // [status, shouldRetry]
        yield '400 -> no retry'  => [400, false];
        yield '401 -> no retry'  => [401, false];
        yield '403 -> no retry'  => [403, false];
        yield '404 -> no retry'  => [404, false];
        yield '409 -> no retry'  => [409, false];
        yield '422 -> no retry'  => [422, false];
        yield '429 -> retry'     => [429, true];
        yield '500 -> retry'     => [500, true];
        yield '502 -> retry'     => [502, true];
        yield '503 -> retry'     => [503, true];
        yield '504 -> retry'     => [504, true];
    }

    #[Test]
    #[DataProvider('statusRetryProvider')]
    public function status_codes_retry_policy(int $status, bool $shouldRetry): void
    {
        $this->http->queue(new Psr7Response($status, [], '{}'));
        if ($shouldRetry) {
            $this->http->queue(new Psr7Response(200, [], '{}'));
        }

        $response = $this->client()->send('GET', 'https://api.example.test/v1/x', [], null);

        if ($shouldRetry) {
            $this->assertSame(200, $response->status, 'Expected the retried call to succeed.');
            $this->assertSame(2, $this->http->attemptCount());
        } else {
            $this->assertSame($status, $response->status);
            $this->assertSame(1, $this->http->attemptCount());
        }
    }

    #[Test]
    public function non_idempotent_post_does_not_retry_on_429(): void
    {
        // Even on a 429, we will not silently double-charge an unkeyed POST.
        $this->http->queue(new Psr7Response(429, [], '{"message":"slow down"}'));

        $response = $this->client()->send('POST', 'https://api.example.test/v1/payments', [], '{}');

        $this->assertSame(429, $response->status);
        $this->assertSame(1, $this->http->attemptCount());
    }

    // -----------------------------------------------------------------------
    //  Retry-After honoring.
    // -----------------------------------------------------------------------

    #[Test]
    public function retry_after_in_seconds_is_honored(): void
    {
        $this->http->queue(new Psr7Response(429, ['Retry-After' => '7'], '{}'));
        $this->http->queue(new Psr7Response(200, [], '{}'));

        $this->client()->send('GET', 'https://api.example.test/v1/x', [], null);

        $this->assertSame([7_000_000], $this->slept, 'Should sleep for exactly the server-supplied 7 seconds.');
    }

    #[Test]
    public function lowercase_retry_after_header_is_honored(): void
    {
        // Regression: previously the lookup was case-sensitive and silently
        // fell back to exponential backoff when the server sent lowercase.
        $this->http->queue(new Psr7Response(429, ['retry-after' => '4'], '{}'));
        $this->http->queue(new Psr7Response(200, [], '{}'));

        $this->client()->send('GET', 'https://api.example.test/v1/x', [], null);

        $this->assertSame([4_000_000], $this->slept);
    }

    #[Test]
    public function non_digit_retry_after_falls_back_to_exponential_backoff(): void
    {
        // The HTTP-date form is part of RFC 7231 but we deliberately only
        // honor delta-seconds today. Pinning the behavior so a future change
        // to parse HTTP-dates is a conscious decision, not an accident.
        $this->http->queue(new Psr7Response(429, ['Retry-After' => 'Wed, 21 Oct 2026 07:28:00 GMT'], '{}'));
        $this->http->queue(new Psr7Response(200, [], '{}'));

        $this->client()->send('GET', 'https://api.example.test/v1/x', [], null);

        $this->assertCount(1, $this->slept);
        $this->assertLessThan(7_000_000, $this->slept[0], 'Backoff fallback must not block for the literal HTTP-date.');
    }

    // -----------------------------------------------------------------------
    //  Retry caps & exhaustion.
    // -----------------------------------------------------------------------

    #[Test]
    public function max_retries_zero_disables_retries(): void
    {
        $this->http->queue(new Psr7Response(500, [], '{}'));

        $response = $this->client(['max_retries' => 0])
            ->send('GET', 'https://api.example.test/v1/x', [], null);

        $this->assertSame(500, $response->status);
        $this->assertSame(1, $this->http->attemptCount());
        $this->assertSame([], $this->slept);
    }

    #[Test]
    public function retry_caps_at_max_retries_then_returns_last_response(): void
    {
        $this->http->queue(new Psr7Response(500, [], '{}'));
        $this->http->queue(new Psr7Response(500, [], '{}'));
        $this->http->queue(new Psr7Response(500, [], '{}'));

        $response = $this->client(['max_retries' => 2])
            ->send('GET', 'https://api.example.test/v1/x', [], null);

        $this->assertSame(500, $response->status, 'Final response after exhaustion is returned to caller.');
        $this->assertSame(3, $this->http->attemptCount(), 'Initial attempt + 2 retries = 3 calls.');
    }

    #[Test]
    public function transport_failure_is_rethrown_after_exhaustion(): void
    {
        $this->http->queue($this->transportError('boom1'));
        $this->http->queue($this->transportError('boom2'));
        $this->http->queue($this->transportError('boom3'));

        try {
            $this->client(['max_retries' => 2])
                ->send('GET', 'https://api.example.test/v1/x', [], null);
            self::fail('Expected TransportException to be rethrown.');
        } catch (TransportException $e) {
            $this->assertSame(3, $this->http->attemptCount());
            $this->assertInstanceOf(ClientExceptionInterface::class, $e->getPrevious());
        }
    }

    // -----------------------------------------------------------------------
    //  Wire shape.
    // -----------------------------------------------------------------------

    #[Test]
    public function it_forwards_method_url_headers_and_body_to_the_psr18_client(): void
    {
        $this->http->queue(new Psr7Response(200, ['Content-Type' => 'application/json'], '{"ok":true}'));

        $response = $this->client()->send(
            'POST',
            'https://api.example.test/v1/customers',
            ['Authorization' => 'Bearer sk', 'Content-Type' => 'application/json'],
            '{"email":"a@b.com"}',
        );

        $this->assertSame(200, $response->status);
        $this->assertSame('{"ok":true}', $response->body);
        $this->assertSame('application/json', $response->headers['Content-Type']);

        $sent = $this->http->requests[0];
        $this->assertSame('POST', $sent->getMethod());
        $this->assertSame('https://api.example.test/v1/customers', (string) $sent->getUri());
        $this->assertSame('Bearer sk', $sent->getHeaderLine('Authorization'));
        $this->assertSame('{"email":"a@b.com"}', (string) $sent->getBody());
    }
}
