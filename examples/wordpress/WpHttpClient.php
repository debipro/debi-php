<?php

/**
 * PSR-18 HTTP client that delegates to WordPress' built-in HTTP API
 * (`wp_remote_request`).
 *
 * Why this exists
 * ---------------
 * The Debi SDK speaks PSR-18 and auto-discovers any client present in the
 * host application. In WordPress that strategy is brittle: multiple plugins
 * can ship different Guzzle versions, the discovery winner depends on load
 * order, and the resulting transport ignores the WP HTTP configuration
 * (proxy constants, `WP_HTTP_BLOCK_EXTERNAL`, `pre_http_request` filters,
 * managed-host SSL pins, etc.).
 *
 * Wrapping `wp_remote_request` keeps every outbound call inside the
 * WordPress transport stack while still presenting a standards-compliant
 * PSR-18 client to the SDK.
 *
 * Usage
 * -----
 *     $psr17 = new \Nyholm\Psr7\Factory\Psr17Factory();
 *     $http  = new \Debi\Examples\WordPress\WpHttpClient($psr17, $psr17);
 *
 *     $debi = new \Debi\DebiClient([
 *         'api_key'     => get_option('my_plugin_debi_api_key'),
 *         'http_client' => new \Debi\HttpClient\DefaultClient([
 *             'http_client'     => $http,
 *             'request_factory' => $psr17,
 *             'stream_factory'  => $psr17,
 *         ]),
 *     ]);
 *
 * Copy this file into your plugin (rename the namespace to your own) — it
 * is reference code, not a SDK-shipped class, because it depends on
 * WordPress globals that do not exist outside a WP runtime.
 */

declare(strict_types=1);

namespace Debi\Examples\WordPress;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class WpHttpClient implements ClientInterface
{
    public const DEFAULT_TIMEOUT_SECONDS = 30;
    public const DEFAULT_REDIRECTS = 5;

    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly int $timeout = self::DEFAULT_TIMEOUT_SECONDS,
        private readonly int $maxRedirects = self::DEFAULT_REDIRECTS,
        private readonly bool $sslVerify = true,
    ) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        if (!function_exists('wp_remote_request')) {
            throw new WpHttpException(
                'wp_remote_request() is unavailable. WpHttpClient must be used inside a WordPress runtime.',
                $request,
            );
        }

        $args = [
            'method'      => $request->getMethod(),
            'headers'     => $this->flattenHeaders($request),
            'body'        => (string) $request->getBody(),
            'timeout'     => $this->timeout,
            'redirection' => $this->maxRedirects,
            'sslverify'   => $this->sslVerify,
            // `httpversion` is advisory; WP picks the best the transport supports.
            'httpversion' => '1.1',
            // `data_format` => 'body' keeps WP from URL-encoding the JSON payload
            // when `body` is a string (the default for non-GET with array body).
            'data_format' => 'body',
        ];

        $result = wp_remote_request((string) $request->getUri(), $args);

        if (is_wp_error($result)) {
            // WP_Error is thrown for transport failures (DNS, timeout, TLS, etc.)
            // — exactly the class of failure PSR-18 calls a NetworkException.
            throw new WpHttpException(
                sprintf('WordPress HTTP transport error: %s', $result->get_error_message()),
                $request,
            );
        }

        return $this->buildResponse($result);
    }

    /**
     * @return array<string, string>
     */
    private function flattenHeaders(RequestInterface $request): array
    {
        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            // PSR-7 allows multi-value headers; WordPress accepts a comma-joined
            // string per header name, which is RFC 7230 §3.2.2 compliant for
            // every header the Debi API sets.
            $headers[$name] = implode(', ', $values);
        }
        return $headers;
    }

    /**
     * @param array<string, mixed> $wpResponse The raw array returned by wp_remote_request.
     */
    private function buildResponse(array $wpResponse): ResponseInterface
    {
        $status = (int) wp_remote_retrieve_response_code($wpResponse);
        $reason = (string) wp_remote_retrieve_response_message($wpResponse);
        $body   = (string) wp_remote_retrieve_body($wpResponse);

        $response = $this->responseFactory
            ->createResponse($status, $reason)
            ->withBody($this->streamFactory->createStream($body));

        // `wp_remote_retrieve_headers` returns a case-insensitive dictionary
        // (WpOrg\Requests\Utility\CaseInsensitiveDictionary on modern WP,
        // Requests_Utility_CaseInsensitiveDictionary on legacy WP). Both
        // expose `getAll()` and are iterable; iterating handles either.
        $headers = wp_remote_retrieve_headers($wpResponse);
        if (is_iterable($headers)) {
            foreach ($headers as $name => $value) {
                $response = $response->withHeader(
                    (string) $name,
                    is_array($value) ? array_map('strval', $value) : (string) $value,
                );
            }
        }

        return $response;
    }
}

/**
 * Minimal PSR-18 NetworkException for transport failures bubbling out of
 * `wp_remote_request`. Kept in the same file so the adapter is a single
 * copy-paste unit.
 */
final class WpHttpException extends \RuntimeException implements NetworkExceptionInterface
{
    public function __construct(
        string $message,
        private readonly RequestInterface $request,
    ) {
        parent::__construct($message);
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
