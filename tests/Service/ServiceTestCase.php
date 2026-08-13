<?php

declare(strict_types=1);

namespace Debi\Tests\Service;

use Debi\ApiRequestor;
use Debi\HttpClient\Response;
use Debi\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * Shared scaffolding for service smoke tests: every concrete test wires a
 * {@see FakeHttpClient} to a real {@see ApiRequestor} so we exercise the same
 * code path the SDK uses in production, and only the network is stubbed.
 *
 * Each test asserts the wire shape (method + URL) for one service method.
 * Body/header behavior is covered exhaustively in {@see \Debi\Tests\ApiRequestorTest};
 * these tests guard against URL drift and verb typos.
 */
abstract class ServiceTestCase extends TestCase
{
    protected const API_BASE = 'https://api.example.test';
    protected const API_KEY = 'sk_test';
    protected const API_VERSION = '2025-10-02';

    protected FakeHttpClient $http;
    protected ApiRequestor $requestor;

    protected function setUp(): void
    {
        $this->http = new FakeHttpClient();
        $this->requestor = new ApiRequestor(
            httpClient: $this->http,
            apiKey: self::API_KEY,
            apiBase: self::API_BASE,
            apiVersion: self::API_VERSION,
        );
    }

    /**
     * Queue a single-resource response envelope (`{"data": {...}}`) with the
     * given `object` discriminator so the SDK can hydrate it.
     *
     * @param array<string,mixed> $extra
     */
    protected function queueObject(string $object, string $id = 'x', int $status = 200, array $extra = []): void
    {
        $payload = ['data' => ['id' => $id, 'object' => $object] + $extra];
        $this->http->queue(new Response($status, json_encode($payload, JSON_THROW_ON_ERROR), []));
    }

    /**
     * Queue a single-resource envelope exactly as given, with no `object`
     * discriminator added. For the handful of endpoints that answer without
     * one, where {@see queueObject()} would stub a shape the API never sends.
     *
     * @param array<string,mixed> $data
     */
    protected function queueRaw(array $data, int $status = 200): void
    {
        $this->http->queue(new Response(
            $status,
            json_encode(['data' => $data], JSON_THROW_ON_ERROR),
            [],
        ));
    }

    /**
     * Queue a list envelope whose items carry no `object` discriminator.
     *
     * @param list<array<string,mixed>> $items
     */
    protected function queueRawList(array $items): void
    {
        $this->http->queue(new Response(
            200,
            json_encode([
                'data' => $items,
                'links' => ['next' => null],
                'meta' => ['next_cursor' => null],
            ], JSON_THROW_ON_ERROR),
            [],
        ));
    }

    protected function queueEmptyList(): void
    {
        $this->http->queue(new Response(
            200,
            '{"data":[],"links":{"next":null},"meta":{"next_cursor":null}}',
            [],
        ));
    }

    /**
     * Assert the most recent call hit the given METHOD + URL exactly.
     */
    protected function assertCalled(string $method, string $path): void
    {
        $call = $this->http->lastCall();
        self::assertSame($method, $call['method'], "Expected {$method} but got {$call['method']}.");
        self::assertSame(self::API_BASE . $path, $call['url']);
    }
}
