<?php

declare(strict_types=1);

namespace Debi\Tests\Service;

use Debi\Resource\Import;
use Debi\Service\ImportService;
use PHPUnit\Framework\Attributes\Test;

final class ImportServiceTest extends ServiceTestCase
{
    private const ID = 'IMB1rRDqkM5X';

    private ImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ImportService($this->requestor);
    }

    #[Test]
    public function all_hits_the_collection_endpoint(): void
    {
        $this->queueEmptyList();
        $this->service->all();
        $this->assertCalled('GET', '/v1/imports');
    }

    #[Test]
    public function retrieve_hits_the_singular_endpoint(): void
    {
        $this->queueObject('import', self::ID);
        $this->assertInstanceOf(Import::class, $this->service->retrieve(self::ID));
        $this->assertCalled('GET', '/v1/imports/' . self::ID);
    }

    #[Test]
    public function create_posts_to_the_base_path(): void
    {
        $this->queueObject('import', self::ID, 201);
        $this->service->create(['resource' => 'customers']);
        $this->assertCalled('POST', '/v1/imports');
    }

    #[Test]
    public function rows_hits_the_nested_collection_endpoint(): void
    {
        $this->queueEmptyList();
        $this->service->rows(self::ID, ['limit' => 50]);
        $this->assertCalled('GET', '/v1/imports/' . self::ID . '/rows?limit=50');
    }
}
