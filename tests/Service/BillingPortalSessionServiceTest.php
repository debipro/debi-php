<?php

declare(strict_types=1);

namespace Debi\Tests\Service;

use Debi\HttpClient\Response;
use Debi\Resource\BillingPortalSession;
use Debi\Service\BillingPortalSessionService;
use PHPUnit\Framework\Attributes\Test;

/**
 * Verified against:
 *   openapi/paths/billing_portal@sessions.yaml
 *   openapi/components/schemas/BillingPortalSession.yaml
 *
 * The spec exposes a single endpoint — POST to create — so the service
 * intentionally does not expose retrieve/list/update/delete.
 */
final class BillingPortalSessionServiceTest extends ServiceTestCase
{
    // ID shape comes from the spec example: `BPS` + 8 alphanumerics.
    private const ID = 'BPS5Z25Agp708';
    private const CUSTOMER_ID = 'CS3Z25Agp708';
    private const CONFIG_ID = 'BPCqXz3a8YbE';

    private BillingPortalSessionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BillingPortalSessionService($this->requestor);
    }

    #[Test]
    public function create_posts_to_the_sessions_endpoint_and_hydrates_with_the_dotted_discriminator(): void
    {
        $this->http->queue(new Response(201, json_encode([
            'data' => [
                'id' => self::ID,
                'object' => 'billing_portal.session',
                'customer_id' => self::CUSTOMER_ID,
                'billing_portal_configuration_id' => self::CONFIG_ID,
                'url' => 'https://billing.debi.pro/p/session/' . self::ID,
                'return_url' => 'https://example.com/account',
                'livemode' => false,
                'created_at' => '2024-02-11T23:17:54-03:00',
                'updated_at' => '2024-02-11T23:17:54-03:00',
            ],
        ], JSON_THROW_ON_ERROR), []));

        $session = $this->service->create([
            'customer_id' => self::CUSTOMER_ID,
            'billing_portal_configuration_id' => self::CONFIG_ID,
            'return_url' => 'https://example.com/account',
        ]);

        $this->assertInstanceOf(BillingPortalSession::class, $session);
        $this->assertSame(self::ID, $session->id);
        $this->assertSame('billing_portal.session', $session->object);
        $this->assertSame(self::CUSTOMER_ID, $session->customer_id);
        $this->assertSame('https://billing.debi.pro/p/session/' . self::ID, $session->url);
        $this->assertCalled('POST', '/v1/billing_portal/sessions');
    }

    #[Test]
    public function service_does_not_expose_a_retrieve_method(): void
    {
        // The spec defines no GET /v1/billing_portal/sessions/{id}; the SDK
        // must not pretend it exists. This guards against re-introducing the
        // fabricated retrieve() that an earlier draft of the SDK shipped.
        $this->assertFalse(
            method_exists(BillingPortalSessionService::class, 'retrieve'),
            'BillingPortalSessionService::retrieve() does not exist in the API spec '
            . '(no GET /v1/billing_portal/sessions/{id}). Do not add it back.',
        );
    }
}
