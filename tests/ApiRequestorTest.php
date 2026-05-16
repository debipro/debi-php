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
    public function it_generates_a_unique_x_request_id_per_call(): void
    {
        $this->http
            ->queue(new Response(200, '{"object":"list","data":[]}', []))
            ->queue(new Response(200, '{"object":"list","data":[]}', []));

        $this->requestor->request('GET', '/v1/customers');
        $this->requestor->request('GET', '/v1/customers');

        $id1 = $this->http->calls[0]['headers']['X-Request-Id'];
        $id2 = $this->http->calls[1]['headers']['X-Request-Id'];

        $this->assertStringStartsWith('req_', $id1);
        $this->assertStringStartsWith('req_', $id2);
        $this->assertNotSame($id1, $id2, 'request ids must be unique per call');
    }

    #[Test]
    public function user_can_override_x_request_id_via_headers(): void
    {
        $this->http->queue(new Response(200, '{"object":"list","data":[]}', []));

        $this->requestor->request(
            'GET',
            '/v1/customers',
            [],
            new RequestOptions(headers: ['X-Request-Id' => 'req_my_trace']),
        );

        $this->assertSame('req_my_trace', $this->http->lastCall()['headers']['X-Request-Id']);
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
}
