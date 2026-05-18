<?php

declare(strict_types=1);

namespace Debi\Tests\Service;

use Debi\Resource\PaymentMethod;
use Debi\Service\PaymentMethodService;
use PHPUnit\Framework\Attributes\Test;

final class PaymentMethodServiceTest extends ServiceTestCase
{
    private const ID = 'PMJODBMZdayP';
    private const CUSTOMER_ID = 'CSjRZ5JqjAw0';

    private PaymentMethodService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PaymentMethodService($this->requestor);
    }

    #[Test]
    public function all_hits_the_collection_endpoint(): void
    {
        $this->queueEmptyList();
        $this->service->all();
        $this->assertCalled('GET', '/v1/payment_methods');
    }

    #[Test]
    public function retrieve_hits_the_singular_endpoint(): void
    {
        $this->queueObject('payment_method', self::ID);
        $this->assertInstanceOf(PaymentMethod::class, $this->service->retrieve(self::ID));
        $this->assertCalled('GET', '/v1/payment_methods/' . self::ID);
    }

    #[Test]
    public function create_posts_to_the_base_path(): void
    {
        $this->queueObject('payment_method', self::ID, 201);
        $this->service->create(['type' => 'card']);
        $this->assertCalled('POST', '/v1/payment_methods');
    }

    #[Test]
    public function search_uses_the_q_query_parameter_documented_by_the_spec(): void
    {
        // The spec uses `q` as the search parameter name, not `query`.
        // openapi/paths/payment_methods@search.yaml
        $this->queueEmptyList();
        $this->service->search(['q' => 'type:card']);
        $this->assertCalled('GET', '/v1/payment_methods/search?q=type%3Acard');
    }

    #[Test]
    public function attach_posts_to_the_attach_subpath_with_customer_in_the_body(): void
    {
        // Spec: POST /v1/payment_methods/{id}/attach  body { customer: "CS..." } → 204
        // Note: NOT under /actions/.
        $this->http->queue(new \Debi\HttpClient\Response(204, '', []));
        $this->service->attach(self::ID, self::CUSTOMER_ID);

        $call = $this->http->lastCall();
        $this->assertCalled('POST', '/v1/payment_methods/' . self::ID . '/attach');
        $this->assertSame('{"customer":"' . self::CUSTOMER_ID . '"}', $call['body']);
    }

    #[Test]
    public function detach_posts_to_the_detach_subpath_with_no_body(): void
    {
        // Spec: POST /v1/payment_methods/{id}/detach  no body → 204
        $this->http->queue(new \Debi\HttpClient\Response(204, '', []));
        $this->service->detach(self::ID);

        $call = $this->http->lastCall();
        $this->assertCalled('POST', '/v1/payment_methods/' . self::ID . '/detach');
        $this->assertNull($call['body']);
    }
}
