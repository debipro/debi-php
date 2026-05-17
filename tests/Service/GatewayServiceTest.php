<?php

declare(strict_types=1);

namespace Debi\Tests\Service;

use Debi\Service\GatewayService;
use PHPUnit\Framework\Attributes\Test;

final class GatewayServiceTest extends ServiceTestCase
{
    private const ID = 'GWBZqKYEK7Y2';

    private GatewayService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GatewayService($this->requestor);
    }

    #[Test]
    public function all_hits_the_collection_endpoint(): void
    {
        $this->queueEmptyList();
        $this->service->all();
        $this->assertCalled('GET', '/v1/gateways');
    }

    #[Test]
    public function enable_and_disable_use_action_endpoints(): void
    {
        $this->queueObject('gateway', self::ID);
        $this->service->enable(self::ID);
        $this->assertCalled('POST', '/v1/gateways/' . self::ID . '/actions/enable');

        $this->queueObject('gateway', self::ID);
        $this->service->disable(self::ID);
        $this->assertCalled('POST', '/v1/gateways/' . self::ID . '/actions/disable');
    }
}
