<?php

declare(strict_types=1);

namespace Debi\Tests;

use Debi\DebiClient;
use Debi\Service\CustomerService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DebiClientTest extends TestCase
{
    #[Test]
    public function it_constructs_with_a_bare_api_key(): void
    {
        $client = new DebiClient('sk_test_abc');
        $this->assertSame(DebiClient::DEFAULT_API_BASE, $client->apiBase());
    }

    #[Test]
    public function it_constructs_with_a_config_array(): void
    {
        $client = new DebiClient([
            'api_key' => 'sk_test_abc',
            'api_base' => DebiClient::DEFAULT_SANDBOX_BASE,
            'api_version' => '2099-01-01',
        ]);
        $this->assertSame(DebiClient::DEFAULT_SANDBOX_BASE, $client->apiBase());
        $this->assertSame('2099-01-01', $client->apiVersion());
    }

    #[Test]
    public function it_strips_trailing_slash_from_api_base(): void
    {
        $client = new DebiClient(['api_key' => 'sk_test_abc', 'api_base' => 'https://x.test/']);
        $this->assertSame('https://x.test', $client->apiBase());
    }

    #[Test]
    public function it_rejects_an_empty_api_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DebiClient('');
    }

    #[Test]
    public function it_rejects_a_missing_api_key_in_config(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DebiClient([]);
    }

    #[Test]
    public function customers_property_returns_a_customer_service(): void
    {
        $client = new DebiClient('sk_test_abc');
        $this->assertInstanceOf(CustomerService::class, $client->customers);
        $this->assertSame($client->customers, $client->customers, 'services must be cached');
    }

    #[Test]
    public function it_rejects_an_unknown_service_name(): void
    {
        $client = new DebiClient('sk_test_abc');
        $this->expectException(\InvalidArgumentException::class);
        /** @phpstan-ignore-next-line — intentional misuse */
        $client->bogus;
    }
}
