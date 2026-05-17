<?php

declare(strict_types=1);

namespace Debi\Tests\Service;

use Debi\Resource\PaymentMethod;
use Debi\Service\PaymentMethodService;
use PHPUnit\Framework\Attributes\Test;

final class PaymentMethodServiceTest extends ServiceTestCase
{
    private const ID = 'PMJODBMZdayP';

    private PaymentMethodService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PaymentMethodService($this->requestor);
    }

    #[Test]
    public function all_hits_the_collection_endpoint(): void
    {
        $this->queueEmptyList();
        $this->service->all();
        $this->assertCalled('GET', '/v1/payment_methods');
    }

    #[Test]
    public function retrieve_hits_the_singular_endpoint(): void
    {
        $this->queueObject('payment_method', self::ID);
        $this->assertInstanceOf(PaymentMethod::class, $this->service->retrieve(self::ID));
        $this->assertCalled('GET', '/v1/payment_methods/' . self::ID);
    }

    #[Test]
    public function create_posts_to_the_base_path(): void
    {
        $this->queueObject('payment_method', self::ID, 201);
        $this->service->create(['type' => 'card']);
        $this->assertCalled('POST', '/v1/payment_methods');
    }

    #[Test]
    public function search_hits_the_search_subpath(): void
    {
        $this->queueEmptyList();
        $this->service->search(['query' => 'type:card']);
        $this->assertCalled('GET', '/v1/payment_methods/search?query=type%3Acard');
    }

    #[Test]
    public function attach_and_detach_use_action_endpoints(): void
    {
        $this->queueObject('payment_method', self::ID);
        $this->service->attach(self::ID);
        $this->assertCalled('POST', '/v1/payment_methods/' . self::ID . '/actions/attach');

        $this->queueObject('payment_method', self::ID);
        $this->service->detach(self::ID);
        $this->assertCalled('POST', '/v1/payment_methods/' . self::ID . '/actions/detach');
    }
}
