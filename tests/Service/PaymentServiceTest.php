<?php

declare(strict_types=1);

namespace Debi\Tests\Service;

use Debi\Resource\Payment;
use Debi\Service\PaymentService;
use PHPUnit\Framework\Attributes\Test;

final class PaymentServiceTest extends ServiceTestCase
{
    private const ID = 'PY8EJ1NdNwzD';

    private PaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PaymentService($this->requestor);
    }

    #[Test]
    public function all_hits_the_collection_endpoint(): void
    {
        $this->queueEmptyList();
        $this->service->all(['limit' => 10]);
        $this->assertCalled('GET', '/v1/payments?limit=10');
    }

    #[Test]
    public function retrieve_hits_the_singular_endpoint(): void
    {
        $this->queueObject('payment', self::ID);
        $this->assertInstanceOf(Payment::class, $this->service->retrieve(self::ID));
        $this->assertCalled('GET', '/v1/payments/' . self::ID);
    }

    #[Test]
    public function create_posts_to_the_base_path(): void
    {
        $this->queueObject('payment', self::ID, 201);
        $this->service->create(['amount' => 100, 'currency' => 'ARS']);
        $this->assertCalled('POST', '/v1/payments');
    }

    #[Test]
    public function update_puts_to_the_singular_endpoint(): void
    {
        $this->queueObject('payment', self::ID);
        $this->service->update(self::ID, ['description' => 'x']);
        $this->assertCalled('PUT', '/v1/payments/' . self::ID);
    }

    #[Test]
    public function search_posts_to_the_search_subpath(): void
    {
        $this->queueEmptyList();
        $this->service->search(['query' => 'status:succeeded']);
        $this->assertCalled('GET', '/v1/payments/search?query=status%3Asucceeded');
    }

    #[Test]
    public function confirm_cancel_retry_and_stop_auto_retrying_use_action_endpoints(): void
    {
        $this->queueObject('payment', self::ID);
        $this->service->confirm(self::ID);
        $this->assertCalled('POST', '/v1/payments/' . self::ID . '/actions/confirm');

        $this->queueObject('payment', self::ID);
        $this->service->cancel(self::ID);
        $this->assertCalled('POST', '/v1/payments/' . self::ID . '/actions/cancel');

        $this->queueObject('payment', self::ID);
        $this->service->retry(self::ID);
        $this->assertCalled('POST', '/v1/payments/' . self::ID . '/actions/retry');

        $this->queueObject('payment', self::ID);
        $this->service->stopAutoRetrying(self::ID);
        $this->assertCalled('POST', '/v1/payments/' . self::ID . '/actions/stop_auto_retrying');
    }
}
