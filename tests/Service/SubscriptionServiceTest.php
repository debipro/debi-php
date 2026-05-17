<?php

declare(strict_types=1);

namespace Debi\Tests\Service;

use Debi\Resource\Subscription;
use Debi\Service\SubscriptionService;
use PHPUnit\Framework\Attributes\Test;

final class SubscriptionServiceTest extends ServiceTestCase
{
    private const ID = 'SBmQ6j9NWxblNv';

    private SubscriptionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SubscriptionService($this->requestor);
    }

    #[Test]
    public function all_hits_the_collection_endpoint(): void
    {
        $this->queueEmptyList();
        $this->service->all();
        $this->assertCalled('GET', '/v1/subscriptions');
    }

    #[Test]
    public function retrieve_hits_the_singular_endpoint(): void
    {
        $this->queueObject('subscription', self::ID);
        $this->assertInstanceOf(Subscription::class, $this->service->retrieve(self::ID));
        $this->assertCalled('GET', '/v1/subscriptions/' . self::ID);
    }

    #[Test]
    public function create_posts_to_the_base_path(): void
    {
        $this->queueObject('subscription', self::ID, 201);
        $this->service->create(['customer_id' => 'CS1', 'amount' => 100, 'currency' => 'ARS']);
        $this->assertCalled('POST', '/v1/subscriptions');
    }

    #[Test]
    public function update_puts_to_the_singular_endpoint(): void
    {
        $this->queueObject('subscription', self::ID);
        $this->service->update(self::ID, ['amount' => 200]);
        $this->assertCalled('PUT', '/v1/subscriptions/' . self::ID);
    }

    #[Test]
    public function search_hits_the_search_subpath(): void
    {
        $this->queueEmptyList();
        $this->service->search(['query' => 'status:active']);
        $this->assertCalled('GET', '/v1/subscriptions/search?query=status%3Aactive');
    }

    #[Test]
    public function cancel_pause_and_resume_use_action_endpoints(): void
    {
        $this->queueObject('subscription', self::ID);
        $this->service->cancel(self::ID);
        $this->assertCalled('POST', '/v1/subscriptions/' . self::ID . '/actions/cancel');

        $this->queueObject('subscription', self::ID);
        $this->service->pause(self::ID);
        $this->assertCalled('POST', '/v1/subscriptions/' . self::ID . '/actions/pause');

        $this->queueObject('subscription', self::ID);
        $this->service->resume(self::ID);
        $this->assertCalled('POST', '/v1/subscriptions/' . self::ID . '/actions/resume');
    }
}
