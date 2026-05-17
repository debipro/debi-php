<?php

declare(strict_types=1);

namespace Debi\Tests\Service;

use Debi\Resource\Link;
use Debi\Service\LinkService;
use PHPUnit\Framework\Attributes\Test;

final class LinkServiceTest extends ServiceTestCase
{
    private const ID = 'LKYeoQ4WbDe9xdRq7j';

    private LinkService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LinkService($this->requestor);
    }

    #[Test]
    public function all_hits_the_collection_endpoint(): void
    {
        $this->queueEmptyList();
        $this->service->all();
        $this->assertCalled('GET', '/v1/links');
    }

    #[Test]
    public function retrieve_hits_the_singular_endpoint(): void
    {
        $this->queueObject('link', self::ID);
        $this->assertInstanceOf(Link::class, $this->service->retrieve(self::ID));
        $this->assertCalled('GET', '/v1/links/' . self::ID);
    }

    #[Test]
    public function create_posts_to_the_base_path(): void
    {
        $this->queueObject('link', self::ID, 201);
        $this->service->create(['kind' => 'payment', 'title' => 'Donation']);
        $this->assertCalled('POST', '/v1/links');
    }

    #[Test]
    public function update_puts_to_the_singular_endpoint(): void
    {
        $this->queueObject('link', self::ID);
        $this->service->update(self::ID, ['title' => 'new title']);
        $this->assertCalled('PUT', '/v1/links/' . self::ID);
    }

    #[Test]
    public function delete_hits_the_singular_endpoint_with_DELETE(): void
    {
        $this->http->queue(new \Debi\HttpClient\Response(204, '', []));
        $this->service->delete(self::ID);
        $this->assertCalled('DELETE', '/v1/links/' . self::ID);
    }

    #[Test]
    public function send_to_customers_uses_the_action_endpoint(): void
    {
        // The action verb is intentionally camelCase on the wire (`sendToCustomers`).
        // Pin it so a future "normalize to snake_case" refactor cannot break it
        // without an explicit test update.
        $this->queueObject('link', self::ID);
        $this->service->sendToCustomers(self::ID, ['customer_ids' => ['CS1']]);
        $this->assertCalled('POST', '/v1/links/' . self::ID . '/actions/sendToCustomers');
    }
}
