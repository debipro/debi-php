<?php

declare(strict_types=1);

namespace Debi\Tests;

use Debi\ApiRequestor;
use Debi\Collection;
use Debi\HttpClient\Response;
use Debi\Resource\Customer;
use Debi\Service\CustomerService;
use Debi\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CollectionTest extends TestCase
{
    #[Test]
    public function it_iterates_a_single_page(): void
    {
        $http = new FakeHttpClient();
        $http->queue(new Response(
            200,
            '{"object":"list","data":[{"id":"cus_1","object":"customer"},{"id":"cus_2","object":"customer"}]}',
            [],
        ));
        $service = new CustomerService(new ApiRequestor($http, 'sk', 'https://x', '2025-10-02'));

        $list = $service->all();
        $ids = [];
        foreach ($list as $c) {
            $ids[] = $c->id;
        }
        $this->assertSame(['cus_1', 'cus_2'], $ids);
    }

    #[Test]
    public function auto_paging_iterator_walks_through_multiple_pages(): void
    {
        $http = new FakeHttpClient();
        $http
            ->queue(new Response(
                200,
                '{"object":"list","data":[{"id":"cus_1","object":"customer"},{"id":"cus_2","object":"customer"}]}',
                [],
            ))
            ->queue(new Response(
                200,
                '{"object":"list","data":[{"id":"cus_3","object":"customer"}]}',
                [],
            ))
            ->queue(new Response(200, '{"object":"list","data":[]}', []));

        $service = new CustomerService(new ApiRequestor($http, 'sk', 'https://x', '2025-10-02'));

        $collected = [];
        foreach ($service->all(['limit' => 2])->autoPagingIterator() as $c) {
            $this->assertInstanceOf(Customer::class, $c);
            $collected[] = $c->id;
        }

        $this->assertSame(['cus_1', 'cus_2', 'cus_3'], $collected);
        $this->assertSame('https://x/v1/customers?limit=2', $http->calls[0]['url']);
        $this->assertSame('https://x/v1/customers?limit=2&starting_after=cus_2', $http->calls[1]['url']);
        $this->assertSame('https://x/v1/customers?limit=2&starting_after=cus_3', $http->calls[2]['url']);
    }

    #[Test]
    public function auto_paging_stops_when_a_page_is_empty(): void
    {
        $http = new FakeHttpClient();
        $http->queue(new Response(200, '{"object":"list","data":[]}', []));

        $service = new CustomerService(new ApiRequestor($http, 'sk', 'https://x', '2025-10-02'));

        $count = 0;
        foreach ($service->all()->autoPagingIterator() as $_) {
            $count++;
        }
        $this->assertSame(0, $count);
        $this->assertCount(1, $http->calls);
    }

    #[Test]
    public function collection_round_trips_to_array(): void
    {
        $list = Collection::constructFrom([
            'object' => 'list',
            'data' => [['id' => 'cus_1', 'object' => 'customer']],
        ]);

        $this->assertSame('list', $list->object);
        $this->assertSame(
            [
                'object' => 'list',
                'data' => [['id' => 'cus_1', 'object' => 'customer']],
            ],
            $list->toArray(),
        );
    }
}
