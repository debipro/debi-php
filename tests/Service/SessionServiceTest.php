<?php

declare(strict_types=1);

namespace Debi\Tests\Service;

use Debi\Resource\Session;
use Debi\Service\SessionService;
use PHPUnit\Framework\Attributes\Test;

final class SessionServiceTest extends ServiceTestCase
{
    private const ID = 'SSmQ6j9NWxblNv';

    private SessionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SessionService($this->requestor);
    }

    #[Test]
    public function create_posts_to_the_base_path(): void
    {
        $this->queueObject('session', self::ID, 201);
        $this->assertInstanceOf(
            Session::class,
            $this->service->create(['kind' => 'subscription']),
        );
        $this->assertCalled('POST', '/v1/sessions');
    }

    #[Test]
    public function retrieve_hits_the_singular_endpoint(): void
    {
        $this->queueObject('session', self::ID);
        $this->service->retrieve(self::ID);
        $this->assertCalled('GET', '/v1/sessions/' . self::ID);
    }
}
