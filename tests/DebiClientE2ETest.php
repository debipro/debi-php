<?php

declare(strict_types=1);

namespace Debi\Tests;

use Debi\Debi;
use Debi\DebiClient;
use Debi\Exception\NotFoundException;
use Debi\HttpClient\Response;
use Debi\Resource\Customer;
use Debi\Resource\Payment;
use Debi\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end wiring test that exercises the entire call stack as a user would
 * use it: construct a {@see DebiClient}, hit a service property, get back a
 * typed Resource. Catches any future regression where the DI between
 * DebiClient -> Service -> ApiRequestor -> ClientInterface drifts.
 */
final class DebiClientE2ETest extends TestCase
{
    private const CUSTOMER_ID = 'CSjRZ5JqjAw0';
    private const PAYMENT_ID = 'PY8EJ1NdNwzD';

    private FakeHttpClient $http;
    private DebiClient $client;

    protected function setUp(): void
    {
        $this->http = new FakeHttpClient();
        $this->client = new DebiClient([
            'api_key' => 'sk_test_e2e',
            'api_base' => 'https://api.example.test',
            'http_client' => $this->http,
        ]);
    }

    #[Test]
    public function it_creates_a_customer_through_the_full_stack(): void
    {
        $this->http->queue(new Response(
            201,
            '{"data":{"id":"' . self::CUSTOMER_ID . '","object":"customer","email":"a@b.com"}}',
            [],
        ));

        $customer = $this->client->customers->create(
            ['email' => 'a@b.com', 'name' => 'Ana'],
            ['idempotency_key' => 'order-1'],
        );

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertSame(self::CUSTOMER_ID, $customer->id);

        $call = $this->http->lastCall();
        $this->assertSame('POST', $call['method']);
        $this->assertSame('https://api.example.test/v1/customers', $call['url']);
        $this->assertSame('Bearer sk_test_e2e', $call['headers']['Authorization']);
        $this->assertSame(Debi::API_VERSION, $call['headers']['Debi-Version']);
        $this->assertSame('order-1', $call['headers']['Idempotency-Key']);
        $this->assertStringContainsString('Debi/', $call['headers']['User-Agent']);
        $this->assertSame('{"email":"a@b.com","name":"Ana"}', $call['body']);
    }

    #[Test]
    public function it_chains_resources_passing_an_object_in_place_of_an_id(): void
    {
        $this->http
            ->queue(new Response(
                201,
                '{"data":{"id":"' . self::CUSTOMER_ID . '","object":"customer"}}',
                [],
            ))
            ->queue(new Response(
                201,
                '{"data":{"id":"' . self::PAYMENT_ID . '","object":"payment","customer_id":"' . self::CUSTOMER_ID . '"}}',
                [],
            ));

        $customer = $this->client->customers->create(['email' => 'a@b.com']);
        $payment = $this->client->payments->create([
            'amount' => 100,
            'currency' => 'ARS',
            'customer_id' => $customer, // intentionally pass the object, not the id
        ]);

        $this->assertInstanceOf(Payment::class, $payment);

        // Util::objectsToIds should have flattened the Customer to its id
        // before the body was JSON-encoded.
        $sentBody = $this->http->calls[1]['body'];
        $this->assertSame(
            '{"amount":100,"currency":"ARS","customer_id":"' . self::CUSTOMER_ID . '"}',
            $sentBody,
        );
    }

    #[Test]
    public function it_maps_a_404_response_to_a_typed_not_found_exception(): void
    {
        $this->http->queue(new Response(404, '{"message":"Record not found."}', []));

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Record not found.');

        $this->client->customers->retrieve('CS0000notFound');
    }

    #[Test]
    public function services_are_cached_for_the_lifetime_of_the_client(): void
    {
        $this->assertSame($this->client->customers, $this->client->customers);
        $this->assertSame($this->client->payments, $this->client->payments);
    }

    #[Test]
    public function it_falls_back_to_the_default_api_base_when_unset(): void
    {
        $client = new DebiClient([
            'api_key' => 'sk_test',
            'http_client' => $this->http,
        ]);

        $this->assertSame(DebiClient::DEFAULT_API_BASE, $client->apiBase());
        $this->assertSame(Debi::API_VERSION, $client->apiVersion());
    }
}
