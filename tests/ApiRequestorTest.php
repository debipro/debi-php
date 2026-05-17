<?php

declare(strict_types=1);

namespace Debi\Tests;

use Debi\ApiRequestor;
use Debi\Debi;
use Debi\Exception\ApiErrorException;
use Debi\Exception\AuthenticationException;
use Debi\Exception\ConflictException;
use Debi\Exception\InvalidRequestException;
use Debi\Exception\NotFoundException;
use Debi\Exception\PermissionException;
use Debi\Exception\RateLimitException;
use Debi\Exception\ServerException;
use Debi\HttpClient\Response;
use Debi\RequestOptions;
use Debi\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ApiRequestorTest extends TestCase
{
    private FakeHttpClient $http;
    private ApiRequestor $requestor;

    protected function setUp(): void
    {
        $this->http = new FakeHttpClient();
        $this->requestor = new ApiRequestor(
            httpClient: $this->http,
            apiKey: 'sk_test_abc',
            apiBase: 'https://api.example.test',
            apiVersion: '2025-10-02',
        );
    }

    #[Test]
    public function it_sends_bearer_token_and_version_and_user_agent(): void
    {
        $this->http->queue(new Response(200, '{"object":"customer","id":"cus_1"}', []));

        $this->requestor->request('GET', '/v1/customers/cus_1');

        $call = $this->http->lastCall();
        $this->assertSame('GET', $call['method']);
        $this->assertSame('https://api.example.test/v1/customers/cus_1', $call['url']);
        $this->assertSame('Bearer sk_test_abc', $call['headers']['Authorization']);
        $this->assertSame('2025-10-02', $call['headers']['Debi-Version']);
        $this->assertSame(Debi::userAgent(), $call['headers']['User-Agent']);
        $this->assertSame('application/json', $call['headers']['Accept']);
        $this->assertNull($call['body']);
    }

    #[Test]
    public function it_encodes_get_params_as_query_string(): void
    {
        $this->http->queue(new Response(200, '{"object":"list","data":[]}', []));

        $this->requestor->request('GET', '/v1/customers', ['limit' => 25, 'created_at' => 12345]);

        $call = $this->http->lastCall();
        $this->assertSame('https://api.example.test/v1/customers?limit=25&created_at=12345', $call['url']);
    }

    #[Test]
    public function it_encodes_post_params_as_json_with_content_type(): void
    {
        $this->http->queue(new Response(201, '{"object":"customer","id":"cus_1"}', []));

        $this->requestor->request('POST', '/v1/customers', ['email' => 'a@b.com']);

        $call = $this->http->lastCall();
        $this->assertSame('POST', $call['method']);
        $this->assertSame('application/json', $call['headers']['Content-Type']);
        $this->assertSame('{"email":"a@b.com"}', $call['body']);
    }

    #[Test]
    public function it_attaches_idempotency_key_when_provided(): void
    {
        $this->http->queue(new Response(201, '{"object":"customer","id":"cus_1"}', []));

        $this->requestor->request(
            'POST',
            '/v1/customers',
            ['email' => 'a@b.com'],
            new RequestOptions(idempotencyKey: 'order-1234'),
        );

        $this->assertSame('order-1234', $this->http->lastCall()['headers']['Idempotency-Key']);
    }

    #[Test]
    public function it_uses_per_request_api_key_override(): void
    {
        $this->http->queue(new Response(200, '{"object":"customer","id":"cus_1"}', []));

        $this->requestor->request(
            'GET',
            '/v1/customers/cus_1',
            [],
            new RequestOptions(apiKey: 'sk_test_override'),
        );

        $this->assertSame('Bearer sk_test_override', $this->http->lastCall()['headers']['Authorization']);
    }

    #[Test]
    public function it_does_not_send_a_client_generated_request_id(): void
    {
        // Request ids are a server-side concern (Stripe-style). The SDK must
        // not synthesize one, both because the server has no use for it today
        // and because pretending to have one would obscure real server-set
        // values once the API starts returning them.
        $this->http->queue(new Response(200, '{"object":"list","data":[]}', []));

        $this->requestor->request('GET', '/v1/customers');

        $sent = $this->http->lastCall()['headers'];
        $this->assertArrayNotHasKey('X-Request-Id', $sent);
        $this->assertArrayNotHasKey('Request-Id', $sent);
    }

    #[Test]
    public function it_applies_custom_headers_from_request_options(): void
    {
        $this->http->queue(new Response(200, '{"object":"list","data":[]}', []));

        $this->requestor->request(
            'GET',
            '/v1/customers',
            [],
            new RequestOptions(headers: ['X-Trace-Span' => 'abc123']),
        );

        $this->assertSame('abc123', $this->http->lastCall()['headers']['X-Trace-Span']);
    }

    /**
     * @return iterable<string, array{int, class-string<ApiErrorException>}>
     */
    public static function statusToExceptionProvider(): iterable
    {
        yield '401 -> Authentication'    => [401, AuthenticationException::class];
        yield '403 -> Permission'        => [403, PermissionException::class];
        yield '404 -> NotFound'          => [404, NotFoundException::class];
        yield '409 -> Conflict'          => [409, ConflictException::class];
        yield '422 -> InvalidRequest'    => [422, InvalidRequestException::class];
        yield '400 -> InvalidRequest'    => [400, InvalidRequestException::class];
        yield '429 -> RateLimit'         => [429, RateLimitException::class];
        yield '500 -> Server'            => [500, ServerException::class];
        yield '502 -> Server'            => [502, ServerException::class];
    }

    /**
     * @param class-string<ApiErrorException> $expected
     */
    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('statusToExceptionProvider')]
    public function it_maps_status_codes_to_specific_exceptions(int $status, string $expected): void
    {
        $this->http->queue(new Response($status, '{"message":"nope"}', []));

        try {
            $this->requestor->request('GET', '/v1/customers');
            self::fail("Expected {$expected} for status {$status}.");
        } catch (ApiErrorException $e) {
            $this->assertInstanceOf($expected, $e);
            $this->assertSame($status, $e->httpStatus);
            $this->assertSame('nope', $e->getMessage());
        }
    }

    #[Test]
    public function invalid_request_exposes_validation_errors(): void
    {
        $this->http->queue(new Response(
            422,
            '{"message":"The given data was invalid.","errors":{"email":["El email es inválido."]}}',
            [],
        ));

        try {
            $this->requestor->request('POST', '/v1/customers', ['email' => '']);
            self::fail('Expected InvalidRequestException');
        } catch (InvalidRequestException $e) {
            $this->assertSame(['email' => 'El email es inválido.'], $e->validationErrors);
        }
    }

    #[Test]
    public function rate_limit_exposes_retry_after(): void
    {
        $this->http->queue(new Response(429, '{"message":"slow down"}', ['Retry-After' => '7']));

        try {
            $this->requestor->request('GET', '/v1/customers');
            self::fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            $this->assertSame(7, $e->retryAfter());
        }
    }

    #[Test]
    public function it_surfaces_a_3xx_redirect_as_a_clear_api_error(): void
    {
        $this->http->queue(new Response(
            301,
            "<html><body>Moved Permanently</body></html>",
            ['Location' => 'https://api.debi.pro/v1/customers'],
        ));

        try {
            $this->requestor->request('POST', '/v1/customers', ['email' => 'a@b.com']);
            self::fail('Expected ApiErrorException');
        } catch (ApiErrorException $e) {
            $this->assertSame(301, $e->httpStatus);
            $this->assertStringContainsString('redirect to https://api.debi.pro/v1/customers', $e->getMessage());
            $this->assertStringContainsString('apiBase', $e->getMessage());
        }
    }

    #[Test]
    public function it_surfaces_a_non_json_5xx_as_a_typed_server_error(): void
    {
        $this->http->queue(new Response(
            502,
            "<html>\n<head><title>502 Bad Gateway</title></head>\n<body><h1>Bad Gateway</h1></body>\n</html>",
            ['Content-Type' => 'text/html'],
        ));

        try {
            $this->requestor->request('GET', '/v1/customers');
            self::fail('Expected ServerException');
        } catch (ServerException $e) {
            $this->assertSame(502, $e->httpStatus);
            $this->assertStringContainsString('non-JSON', $e->getMessage());
            $this->assertStringContainsString('502 Bad Gateway', $e->getMessage());
        }
    }

    #[Test]
    public function it_truncates_long_non_json_bodies_in_the_preview(): void
    {
        $huge = str_repeat('A', 1000);
        $this->http->queue(new Response(502, $huge, []));

        try {
            $this->requestor->request('GET', '/v1/customers');
            self::fail('Expected ServerException');
        } catch (ServerException $e) {
            $this->assertStringContainsString('…', $e->getMessage());
            $this->assertLessThan(400, strlen($e->getMessage()));
        }
    }

    #[Test]
    public function it_does_not_treat_a_2xx_json_response_as_an_error(): void
    {
        $this->http->queue(new Response(200, '{"data":{"id":"CSjRZ5JqjAw0","object":"customer"}}', []));

        [$body, , $status] = $this->requestor->request('GET', '/v1/customers/CSjRZ5JqjAw0');

        $this->assertSame(200, $status);
        $this->assertSame('CSjRZ5JqjAw0', $body['data']['id']);
    }

    #[Test]
    public function it_throws_on_2xx_with_a_non_json_body(): void
    {
        // A 2xx with a non-empty body that does not parse as JSON is almost
        // always a misconfiguration (proxy stripping the body, middleware
        // injecting HTML, an upstream redirect page leaking through with a
        // 200). The SDK surfaces this as a typed error rather than swallowing
        // it: silently returning an empty array would mask the bug and show
        // up as missing fields somewhere downstream.
        $this->http->queue(new Response(
            200,
            '<html>not json</html>',
            ['Content-Type' => 'text/html'],
        ));

        try {
            $this->requestor->request('GET', '/v1/customers');
            self::fail('Expected ApiErrorException for 2xx non-JSON body');
        } catch (ApiErrorException $e) {
            $this->assertSame(200, $e->httpStatus);
            $this->assertStringContainsString('non-JSON', $e->getMessage());
            $this->assertStringContainsString('not json', $e->getMessage());
        }
    }

    #[Test]
    public function it_tolerates_2xx_with_an_empty_body(): void
    {
        // 202/204 with no payload is the conventional success shape for
        // DELETE and several action endpoints. Treat the empty body as an
        // empty result rather than a malformed response.
        $this->http->queue(new Response(204, '', []));

        [$body, , $status] = $this->requestor->request('DELETE', '/v1/customers/CSjRZ5JqjAw0');

        $this->assertSame(204, $status);
        $this->assertSame([], $body);
    }
}
