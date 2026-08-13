<?php

declare(strict_types=1);

namespace Debi\Tests\Service;

use Debi\Resource\Customer;
use Debi\Resource\Subscription;
use Debi\Service\SubscriptionService;
use PHPUnit\Framework\Attributes\Test;

final class SubscriptionServiceTest extends ServiceTestCase
{
    private const ID = 'SBmQ6j9NWxblNv';
    private const CUSTOMER_ID = 'CSjRZ5JqjAw0';

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
    public function retrieve_exposes_the_customer_expanded_rather_than_as_an_id(): void
    {
        // A subscription carries no `customer_id` scalar. Code reaching for one
        // gets a silent null, so the expanded shape is pinned here.
        $this->queueObject('subscription', self::ID, 200, [
            'customer' => ['id' => self::CUSTOMER_ID, 'object' => 'customer', 'email' => 'a@b.com'],
        ]);

        $subscription = $this->service->retrieve(self::ID);

        $this->assertInstanceOf(Customer::class, $subscription->customer);
        $this->assertSame(self::CUSTOMER_ID, $subscription->customer->id);
        // Array access rather than `->customer_id`, which now warns by design.
        $this->assertNull($subscription['customer_id']);
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
        $this->service->search(['q' => 'status:active']);
        $this->assertCalled('GET', '/v1/subscriptions/search?q=status%3Aactive');
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
