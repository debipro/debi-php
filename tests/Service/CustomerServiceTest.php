<?php

declare(strict_types=1);

namespace Debi\Tests\Service;

use Debi\ApiRequestor;
use Debi\Collection;
use Debi\HttpClient\Response;
use Debi\Resource\Customer;
use Debi\Service\CustomerService;
use Debi\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CustomerServiceTest extends TestCase
{
    // Real ID prefix shape observed from the live API: `CS` + 10 alphanumerics.
    private const CUSTOMER_ID_1 = 'CSjRZ5JqjAw0';
    private const CUSTOMER_ID_2 = 'CSkywYrxQYDR';

    private FakeHttpClient $http;
    private CustomerService $service;

    protected function setUp(): void
    {
        $this->http = new FakeHttpClient();
        $requestor = new ApiRequestor(
            httpClient: $this->http,
            apiKey: 'sk_test',
            apiBase: 'https://api.example.test',
            apiVersion: '2025-10-02',
        );
        $this->service = new CustomerService($requestor);
    }

    #[Test]
    public function it_creates_a_customer(): void
    {
        $this->http->queue(new Response(
            201,
            '{"data":{"id":"' . self::CUSTOMER_ID_1 . '","object":"customer","livemode":false,'
            . '"email":"a@b.com","name":"Ana"}}',
            [],
        ));

        $customer = $this->service->create([
            'email' => 'a@b.com',
            'name' => 'Ana',
        ], ['idempotency_key' => 'order-1']);

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertSame(self::CUSTOMER_ID_1, $customer->id);
        $this->assertSame('a@b.com', $customer->email);

        $call = $this->http->lastCall();
        $this->assertSame('POST', $call['method']);
        $this->assertSame('https://api.example.test/v1/customers', $call['url']);
        $this->assertSame('order-1', $call['headers']['Idempotency-Key']);
        $this->assertSame('{"email":"a@b.com","name":"Ana"}', $call['body']);
    }

    #[Test]
    public function it_retrieves_a_customer(): void
    {
        $this->http->queue(new Response(
            200,
            '{"data":{"id":"' . self::CUSTOMER_ID_1 . '","object":"customer"}}',
            [],
        ));

        $customer = $this->service->retrieve(self::CUSTOMER_ID_1);

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertSame(self::CUSTOMER_ID_1, $customer->id);
        $this->assertSame(
            'https://api.example.test/v1/customers/' . self::CUSTOMER_ID_1,
            $this->http->lastCall()['url'],
        );
    }

    #[Test]
    public function it_updates_a_customer(): void
    {
        $this->http->queue(new Response(
            200,
            '{"data":{"id":"' . self::CUSTOMER_ID_1 . '","object":"customer","name":"new"}}',
            [],
        ));

        $this->service->update(self::CUSTOMER_ID_1, ['name' => 'new']);

        $call = $this->http->lastCall();
        $this->assertSame('PUT', $call['method']);
        $this->assertSame('{"name":"new"}', $call['body']);
    }

    #[Test]
    public function it_lists_customers_with_query_params(): void
    {
        $this->http->queue(new Response(
            200,
            '{"data":['
            . '{"id":"' . self::CUSTOMER_ID_1 . '","object":"customer"},'
            . '{"id":"' . self::CUSTOMER_ID_2 . '","object":"customer"}'
            . '],"links":{"next":null},"meta":{"next_cursor":null}}',
            [],
        ));

        $list = $this->service->all(['limit' => 10]);

        $this->assertInstanceOf(Collection::class, $list);
        $this->assertCount(2, $list->data());
        $this->assertSame(
            'https://api.example.test/v1/customers?limit=10',
            $this->http->lastCall()['url'],
        );
    }

    #[Test]
    public function it_searches_customers(): void
    {
        $this->http->queue(new Response(200, '{"data":[],"links":{"next":null},"meta":{}}', []));

        $this->service->search(['query' => 'email:"a@b.com"']);

        $this->assertStringContainsString('/v1/customers/search', $this->http->lastCall()['url']);
    }

    #[Test]
    public function it_archives_and_restores_via_action_endpoints(): void
    {
        $this->http
            ->queue(new Response(
                200,
                '{"data":{"id":"' . self::CUSTOMER_ID_1 . '","object":"customer","archived":true}}',
                [],
            ))
            ->queue(new Response(
                200,
                '{"data":{"id":"' . self::CUSTOMER_ID_1 . '","object":"customer","archived":false}}',
                [],
            ));

        $this->service->archive(self::CUSTOMER_ID_1);
        $this->service->restore(self::CUSTOMER_ID_1);

        $this->assertSame(
            'https://api.example.test/v1/customers/' . self::CUSTOMER_ID_1 . '/actions/archive',
            $this->http->calls[0]['url'],
        );
        $this->assertSame('POST', $this->http->calls[0]['method']);
        $this->assertSame(
            'https://api.example.test/v1/customers/' . self::CUSTOMER_ID_1 . '/actions/restore',
            $this->http->calls[1]['url'],
        );
    }

    #[Test]
    public function it_lists_payment_methods_for_a_customer(): void
    {
        $this->http->queue(new Response(200, '{"data":[],"links":{"next":null},"meta":{}}', []));

        $this->service->paymentMethods(self::CUSTOMER_ID_1);

        $this->assertSame(
            'https://api.example.test/v1/customers/' . self::CUSTOMER_ID_1 . '/payment_methods',
            $this->http->lastCall()['url'],
        );
    }
}
