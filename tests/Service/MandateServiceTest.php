<?php

declare(strict_types=1);

namespace Debi\Tests\Service;

use Debi\Resource\Mandate;
use Debi\Service\MandateService;
use PHPUnit\Framework\Attributes\Test;

final class MandateServiceTest extends ServiceTestCase
{
    private const ID = 'MAmQ6j9NWxblNv';

    private MandateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MandateService($this->requestor);
    }

    #[Test]
    public function all_hits_the_collection_endpoint(): void
    {
        $this->queueEmptyList();
        $this->service->all();
        $this->assertCalled('GET', '/v1/mandates');
    }

    #[Test]
    public function retrieve_hits_the_singular_endpoint(): void
    {
        $this->queueObject('mandate', self::ID);
        $this->assertInstanceOf(Mandate::class, $this->service->retrieve(self::ID));
        $this->assertCalled('GET', '/v1/mandates/' . self::ID);
    }

    #[Test]
    public function create_posts_to_the_base_path(): void
    {
        $this->queueObject('mandate', self::ID, 201);
        $this->service->create(['customer_id' => 'CS1']);
        $this->assertCalled('POST', '/v1/mandates');
    }

    #[Test]
    public function search_hits_the_search_subpath(): void
    {
        $this->queueEmptyList();
        $this->service->search(['q' => 'status:active']);
        $this->assertCalled('GET', '/v1/mandates/search?q=status%3Aactive');
    }

    #[Test]
    public function revoke_and_restore_use_action_endpoints(): void
    {
        $this->queueObject('mandate', self::ID);
        $this->service->revoke(self::ID);
        $this->assertCalled('POST', '/v1/mandates/' . self::ID . '/actions/revoke');

        $this->queueObject('mandate', self::ID);
        $this->service->restore(self::ID);
        $this->assertCalled('POST', '/v1/mandates/' . self::ID . '/actions/restore');
    }
}
