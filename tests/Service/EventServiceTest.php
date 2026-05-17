<?php

declare(strict_types=1);

namespace Debi\Tests\Service;

use Debi\Resource\Event;
use Debi\Service\EventService;
use PHPUnit\Framework\Attributes\Test;

final class EventServiceTest extends ServiceTestCase
{
    private const ID = 'EV1rRDBDOEJM';

    private EventService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EventService($this->requestor);
    }

    #[Test]
    public function all_hits_the_collection_endpoint(): void
    {
        $this->queueEmptyList();
        $this->service->all(['type' => 'payment.succeeded']);
        $this->assertCalled('GET', '/v1/events?type=payment.succeeded');
    }

    #[Test]
    public function retrieve_hits_the_singular_endpoint(): void
    {
        $this->queueObject('event', self::ID);
        $this->assertInstanceOf(Event::class, $this->service->retrieve(self::ID));
        $this->assertCalled('GET', '/v1/events/' . self::ID);
    }
}
