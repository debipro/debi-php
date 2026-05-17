<?php

declare(strict_types=1);

namespace Debi\Tests\Service;

use Debi\Resource\Export;
use Debi\Service\ExportService;
use PHPUnit\Framework\Attributes\Test;

final class ExportServiceTest extends ServiceTestCase
{
    private const ID = 'EXabc1234567';

    private ExportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ExportService($this->requestor);
    }

    #[Test]
    public function all_hits_the_collection_endpoint(): void
    {
        $this->queueEmptyList();
        $this->service->all();
        $this->assertCalled('GET', '/v1/exports');
    }

    #[Test]
    public function retrieve_hits_the_singular_endpoint(): void
    {
        $this->queueObject('export', self::ID);
        $this->assertInstanceOf(Export::class, $this->service->retrieve(self::ID));
        $this->assertCalled('GET', '/v1/exports/' . self::ID);
    }

    #[Test]
    public function create_posts_to_the_base_path(): void
    {
        $this->queueObject('export', self::ID, 201);
        $this->service->create(['resource' => 'payments']);
        $this->assertCalled('POST', '/v1/exports');
    }
}
