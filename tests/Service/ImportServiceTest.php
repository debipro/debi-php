<?php

declare(strict_types=1);

namespace Debi\Tests\Service;

use Debi\DebiObject;
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

    #[Test]
    public function retrieve_hydrates_an_import_without_an_object_discriminator(): void
    {
        // See the equivalent export test: imports answer without `object`, so
        // the service names the class rather than leaning on the map.
        $this->queueRaw(['id' => self::ID, 'type' => 'customers', 'status' => 'ready']);

        $import = $this->service->retrieve(self::ID);

        $this->assertInstanceOf(Import::class, $import);
        $this->assertSame(self::ID, $import->id);
    }

    #[Test]
    public function all_hydrates_list_items_without_an_object_discriminator(): void
    {
        $this->queueRawList([['id' => self::ID, 'type' => 'customers', 'status' => 'ready']]);

        $items = $this->service->all()->data();

        $this->assertInstanceOf(Import::class, $items[0]);
    }

    #[Test]
    public function rows_are_not_hydrated_as_imports(): void
    {
        // An import's rows are not imports. The explicit hydration class must
        // not leak from all()/retrieve() into the nested rows collection.
        $this->queueRawList([['id' => 'IRabc', 'valid' => false]]);

        $items = $this->service->rows(self::ID)->data();

        $this->assertInstanceOf(DebiObject::class, $items[0]);
        $this->assertNotInstanceOf(Import::class, $items[0]);
    }
}
