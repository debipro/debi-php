<?php

declare(strict_types=1);

namespace Debi\Tests\Service;

use Debi\Resource\Refund;
use Debi\Service\RefundService;
use PHPUnit\Framework\Attributes\Test;

final class RefundServiceTest extends ServiceTestCase
{
    private const ID = 'RFljikas9Fa8';

    private RefundService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RefundService($this->requestor);
    }

    #[Test]
    public function all_hits_the_collection_endpoint(): void
    {
        $this->queueEmptyList();
        $this->service->all();
        $this->assertCalled('GET', '/v1/refunds');
    }

    #[Test]
    public function retrieve_hits_the_singular_endpoint(): void
    {
        $this->queueObject('refund', self::ID);
        $this->assertInstanceOf(Refund::class, $this->service->retrieve(self::ID));
        $this->assertCalled('GET', '/v1/refunds/' . self::ID);
    }

    #[Test]
    public function create_posts_to_the_base_path(): void
    {
        $this->queueObject('refund', self::ID, 201);
        $this->service->create(['payment_id' => 'PY1', 'amount' => 100]);
        $this->assertCalled('POST', '/v1/refunds');
    }
}
