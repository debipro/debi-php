<?php

declare(strict_types=1);

namespace Debi;

use Debi\Exception\ApiErrorException;
use Debi\HttpClient\ClientInterface;
use Debi\HttpClient\Response;

/**
 * The only class in the SDK that constructs HTTP requests.
 *
 * Services hand it a method, path, and parameter array; it returns a decoded
 * JSON body plus headers/status. Everything cross-cutting — authentication,
 * version pinning, user-agent, idempotency, header overrides, status-to-
 * exception mapping — lives here, so no service ever has to think about it.
 *
 * @internal
 */
final class ApiRequestor
{
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly string $apiKey,
        private readonly string $apiBase,
        private readonly string $apiVersion,
    ) {}

    /**
     * @param array<int|string,mixed> $params
     *
     * @return array{0: array<int|string,mixed>, 1: array<string,string>, 2: int}
     */
    public function request(
        string $method,
        string $path,
        array $params = [],
        ?RequestOptions $opts = null,
    ): array {
        $opts ??= new RequestOptions();

        $url = $this->apiBase . $path;
        $headers = $this->buildHeaders($method, $opts);

        $body = null;
        if (strtoupper($method) === 'GET') {
            $flatParams = Util\Util::objectsToIds($params);
            if ($flatParams !== []) {
                $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($flatParams);
            }
        } elseif ($params !== []) {
            $headers['Content-Type'] = 'application/json';
            $body = $this->encodeJson(Util\Util::objectsToIds($params));
        }

        $response = $this->httpClient->send($method, $url, $headers, $body);

        return $this->interpretResponse($response);
    }

    /**
     * @return array<string, string>
     */
    private function buildHeaders(string $method, RequestOptions $opts): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . ($opts->apiKey ?? $this->apiKey),
            // The Debi API does not currently read this header, but pinning a
            // version per SDK release is forward-compatible: when dated API
            // versioning is rolled out server-side, every existing SDK install
            // will keep behaving as it does today.
            'Debi-Version' => $opts->apiVersion ?? $this->apiVersion,
            'User-Agent' => Debi::userAgent(),
            'Accept' => 'application/json',
            // Sent on every request so server-side traces (InfluxDB, Sentry,
            // event store) can correlate the request across systems. Users
            // can override by passing `headers: ['X-Request-Id' => '...']`
            // in RequestOptions.
            'X-Request-Id' => self::generateRequestId(),
        ];

        if ($opts->idempotencyKey !== null) {
            $headers['Idempotency-Key'] = $opts->idempotencyKey;
        }

        foreach ($opts->headers as $name => $value) {
            $headers[$name] = $value;
        }

        return $headers;
    }

    private static function generateRequestId(): string
    {
        return 'req_' . bin2hex(random_bytes(16));
    }

    /**
     * @param array<int|string,mixed> $value
     */
    private function encodeJson(array $value): string
    {
        try {
            $encoded = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException(
                'Could not encode request body as JSON: ' . $e->getMessage(),
                0,
                $e,
            );
        }
        return $encoded;
    }

    /**
     * @return array{0: array<int|string,mixed>, 1: array<string,string>, 2: int}
     */
    private function interpretResponse(Response $response): array
    {
        $decoded = [];
        if ($response->body !== '') {
            try {
                $decoded = json_decode($response->body, true, flags: JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                if ($response->status >= 400) {
                    throw ApiErrorException::fromResponse($response->status, null, $response->headers);
                }
                throw new \UnexpectedValueException(
                    'Could not decode Debi API response as JSON: ' . $e->getMessage(),
                    0,
                    $e,
                );
            }
        }

        if (!is_array($decoded)) {
            $decoded = [];
        }

        if ($response->status >= 400) {
            throw ApiErrorException::fromResponse($response->status, $decoded, $response->headers);
        }

        return [$decoded, $response->headers, $response->status];
    }
}
