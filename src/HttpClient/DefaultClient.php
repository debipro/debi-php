<?php

declare(strict_types=1);

namespace Debi\HttpClient;

use Debi\Exception\TransportException;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface as Psr18Client;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Default transport: wraps any PSR-18 client found in the host application
 * (auto-discovered via `php-http/discovery`) and applies a small, safe retry
 * policy. Users can supply their own PSR-18 client, request factory, and
 * stream factory via constructor arguments to override the discovery.
 *
 * Retry policy
 * ------------
 * - Up to {@see DefaultClient::$maxRetries} retries on transport failures,
 *   HTTP 429, and HTTP 5xx, using exponential backoff with jitter.
 * - GET, HEAD, PUT, and DELETE are always considered idempotent and retried.
 * - POST is retried **only** if the request carries an `Idempotency-Key`
 *   header — otherwise we never silently double-charge a customer.
 */
final class DefaultClient implements ClientInterface
{
    public const DEFAULT_MAX_RETRIES = 2;
    public const DEFAULT_INITIAL_BACKOFF_MS = 500;
    public const DEFAULT_MAX_BACKOFF_MS = 4_000;

    private Psr18Client $http;
    private RequestFactoryInterface $requestFactory;
    private StreamFactoryInterface $streamFactory;
    private int $maxRetries;
    private int $initialBackoffMs;
    private int $maxBackoffMs;

    /**
     * @param array{
     *     http_client?: Psr18Client,
     *     request_factory?: RequestFactoryInterface,
     *     stream_factory?: StreamFactoryInterface,
     *     max_retries?: int,
     *     initial_backoff_ms?: int,
     *     max_backoff_ms?: int,
     * } $config
     */
    public function __construct(array $config = [])
    {
        $this->http = $config['http_client'] ?? Psr18ClientDiscovery::find();
        $this->requestFactory = $config['request_factory'] ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->streamFactory = $config['stream_factory'] ?? Psr17FactoryDiscovery::findStreamFactory();
        $this->maxRetries = max(0, $config['max_retries'] ?? self::DEFAULT_MAX_RETRIES);
        $this->initialBackoffMs = max(0, $config['initial_backoff_ms'] ?? self::DEFAULT_INITIAL_BACKOFF_MS);
        $this->maxBackoffMs = max(0, $config['max_backoff_ms'] ?? self::DEFAULT_MAX_BACKOFF_MS);
    }

    public function send(string $method, string $url, array $headers, ?string $body): Response
    {
        $isIdempotent = $this->isRetryable($method, $headers);

        $attempt = 0;
        $lastException = null;
        while (true) {
            try {
                $response = $this->dispatch($method, $url, $headers, $body);

                if ($isIdempotent && $this->shouldRetryStatus($response->status) && $attempt < $this->maxRetries) {
                    $this->sleepForRetry($attempt, $response);
                    $attempt++;
                    continue;
                }
                return $response;
            } catch (TransportException $e) {
                $lastException = $e;
                if ($isIdempotent && $attempt < $this->maxRetries) {
                    $this->sleepForRetry($attempt, null);
                    $attempt++;
                    continue;
                }
                throw $e;
            }
        }
    }

    /**
     * @param array<string,string> $headers
     */
    private function dispatch(string $method, string $url, array $headers, ?string $body): Response
    {
        $request = $this->requestFactory->createRequest($method, $url);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        if ($body !== null) {
            $request = $request->withBody($this->streamFactory->createStream($body));
        }

        try {
            $psrResponse = $this->http->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new TransportException(
                'HTTP transport error contacting Debi: ' . $e->getMessage(),
                0,
                $e,
            );
        }

        $flatHeaders = [];
        foreach ($psrResponse->getHeaders() as $name => $values) {
            $flatHeaders[$name] = implode(', ', $values);
        }

        return new Response(
            status: $psrResponse->getStatusCode(),
            body: (string) $psrResponse->getBody(),
            headers: $flatHeaders,
        );
    }

    /**
     * @param array<string,string> $headers
     */
    private function isRetryable(string $method, array $headers): bool
    {
        $upper = strtoupper($method);
        if (in_array($upper, ['GET', 'HEAD', 'PUT', 'DELETE'], true)) {
            return true;
        }
        if ($upper === 'POST') {
            foreach ($headers as $k => $_v) {
                if (strcasecmp($k, 'Idempotency-Key') === 0) {
                    return true;
                }
            }
        }
        return false;
    }

    private function shouldRetryStatus(int $status): bool
    {
        return $status === 429 || $status >= 500;
    }

    private function sleepForRetry(int $attempt, ?Response $response): void
    {
        if ($response !== null && isset($response->headers['Retry-After'])) {
            $retryAfter = $response->headers['Retry-After'];
            if (ctype_digit($retryAfter)) {
                usleep(((int) $retryAfter) * 1_000_000);
                return;
            }
        }

        $backoff = min($this->maxBackoffMs, $this->initialBackoffMs * (2 ** $attempt));
        $jitter = random_int(0, (int) ($backoff / 2));
        usleep(($backoff + $jitter) * 1_000);
    }
}
