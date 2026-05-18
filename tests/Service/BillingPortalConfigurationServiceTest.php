<?php

declare(strict_types=1);

namespace Debi\Tests\Service;

use Debi\HttpClient\Response;
use Debi\Resource\BillingPortalConfiguration;
use Debi\Service\BillingPortalConfigurationService;
use PHPUnit\Framework\Attributes\Test;

/**
 * Verified against:
 *   openapi/paths/billing_portal@configurations.yaml
 *   openapi/paths/billing_portal@configurations@{id}.yaml
 *   openapi/components/schemas/BillingPortalConfiguration.yaml
 */
final class BillingPortalConfigurationServiceTest extends ServiceTestCase
{
    // ID shape comes from the spec example: `BPC` + 8 alphanumerics.
    private const ID = 'BPCqXz3a8YbE';

    private BillingPortalConfigurationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BillingPortalConfigurationService($this->requestor);
    }

    #[Test]
    public function all_lists_configurations(): void
    {
        $this->queueEmptyList();
        $this->service->all(['active' => 'true']);
        $this->assertCalled('GET', '/v1/billing_portal/configurations?active=true');
    }

    #[Test]
    public function create_posts_and_hydrates_with_the_dotted_discriminator(): void
    {
        $this->http->queue(new Response(201, json_encode([
            'data' => [
                'id' => self::ID,
                'object' => 'billing_portal.configuration',
                'active' => true,
                'is_default' => true,
                'features' => [
                    'customer_update' => ['enabled' => false],
                    'invoice_history' => ['enabled' => true],
                    'payment_method_update' => ['enabled' => true],
                    'subscription_cancel' => ['enabled' => true],
                ],
                'login_page' => ['enabled' => true],
                'livemode' => false,
                'created_at' => '2024-02-11T23:17:54-03:00',
                'updated_at' => '2024-02-11T23:17:54-03:00',
            ],
        ], JSON_THROW_ON_ERROR), []));

        $config = $this->service->create([
            'features' => [
                'customer_update' => false,
                'invoice_history' => true,
                'payment_method_update' => true,
                'subscription_cancel' => true,
            ],
            'login_page' => ['enabled' => true],
        ]);

        $this->assertInstanceOf(BillingPortalConfiguration::class, $config);
        $this->assertSame(self::ID, $config->id);
        $this->assertSame('billing_portal.configuration', $config->object);
        $this->assertTrue($config->is_default);
        $this->assertCalled('POST', '/v1/billing_portal/configurations');
    }

    #[Test]
    public function retrieve_hits_the_singular_endpoint(): void
    {
        $this->queueObject('billing_portal.configuration', self::ID);
        $this->service->retrieve(self::ID);
        $this->assertCalled('GET', '/v1/billing_portal/configurations/' . self::ID);
    }

    #[Test]
    public function update_puts_to_the_singular_endpoint(): void
    {
        $this->queueObject('billing_portal.configuration', self::ID);
        $this->service->update(self::ID, [
            'features' => [
                'customer_update' => true,
                'invoice_history' => true,
                'payment_method_update' => true,
                'subscription_cancel' => false,
            ],
            'login_page' => ['enabled' => true],
        ]);

        $call = $this->http->lastCall();
        $this->assertSame('PUT', $call['method']);
        $this->assertCalled('PUT', '/v1/billing_portal/configurations/' . self::ID);
    }
}
