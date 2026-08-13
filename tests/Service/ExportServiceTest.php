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

    #[Test]
    public function retrieve_hydrates_an_export_without_an_object_discriminator(): void
    {
        // Exports are one of the few endpoints that answer without `object`.
        // Hydration by discriminator alone yields a bare DebiObject, which the
        // declared `: Export` return type rejects with a TypeError — so the
        // service names the class explicitly. Stubbing the real, undiscriminated
        // shape is the only way this stays honest.
        $this->queueRaw(['id' => self::ID, 'type' => 'payments', 'status' => 'ready']);

        $export = $this->service->retrieve(self::ID);

        $this->assertInstanceOf(Export::class, $export);
        $this->assertSame(self::ID, $export->id);
    }

    #[Test]
    public function all_hydrates_list_items_without_an_object_discriminator(): void
    {
        $this->queueRawList([['id' => self::ID, 'type' => 'payments', 'status' => 'ready']]);

        $items = $this->service->all()->data();

        $this->assertInstanceOf(Export::class, $items[0]);
    }
}
