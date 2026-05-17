<?php

declare(strict_types=1);

namespace Debi\Tests\Service;

use Debi\HttpClient\Response;
use Debi\Resource\WebhookEndpoint;
use Debi\Service\WebhookEndpointService;
use PHPUnit\Framework\Attributes\Test;

final class WebhookEndpointServiceTest extends ServiceTestCase
{
    private const ID = 'WHabcdef1234';

    private WebhookEndpointService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WebhookEndpointService($this->requestor);
    }

    #[Test]
    public function all_hits_the_collection_endpoint(): void
    {
        $this->queueEmptyList();
        $this->service->all();
        $this->assertCalled('GET', '/v1/webhooks');
    }

    #[Test]
    public function retrieve_hits_the_singular_endpoint(): void
    {
        // The discriminator is `webhook` even though the path is `/v1/webhooks`.
        $this->queueObject('webhook', self::ID);
        $this->assertInstanceOf(WebhookEndpoint::class, $this->service->retrieve(self::ID));
        $this->assertCalled('GET', '/v1/webhooks/' . self::ID);
    }

    #[Test]
    public function create_posts_to_the_base_path(): void
    {
        $this->queueObject('webhook', self::ID, 201);
        $this->service->create(['url' => 'https://x.example/hook', 'enabled_events' => ['*']]);
        $this->assertCalled('POST', '/v1/webhooks');
    }

    #[Test]
    public function update_puts_to_the_singular_endpoint(): void
    {
        $this->queueObject('webhook', self::ID);
        $this->service->update(self::ID, ['enabled_events' => ['customer.created']]);
        $this->assertCalled('PUT', '/v1/webhooks/' . self::ID);
    }

    #[Test]
    public function delete_hits_the_singular_endpoint_with_DELETE(): void
    {
        $this->http->queue(new Response(204, '', []));
        $this->service->delete(self::ID);
        $this->assertCalled('DELETE', '/v1/webhooks/' . self::ID);
    }
}
