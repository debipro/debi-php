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
    private const ID_1 = 'CSjRZ5JqjAw0';
    private const ID_2 = 'CSkywYrxQYDR';
    private const ID_3 = 'CSa3Z2bvAAZ4';

    #[Test]
    public function it_iterates_a_single_page(): void
    {
        $http = new FakeHttpClient();
        $http->queue(new Response(
            200,
            '{"data":['
            . '{"id":"' . self::ID_1 . '","object":"customer"},'
            . '{"id":"' . self::ID_2 . '","object":"customer"}'
            . '],"links":{"next":null},"meta":{"next_cursor":null}}',
            [],
        ));
        $service = new CustomerService(new ApiRequestor($http, 'sk', 'https://x', '2025-10-02'));

        $list = $service->all();
        $ids = [];
        foreach ($list as $c) {
            $ids[] = $c->id;
        }
        $this->assertSame([self::ID_1, self::ID_2], $ids);
        $this->assertFalse($list->hasMore());
        $this->assertNull($list->nextCursor());
    }

    #[Test]
    public function auto_paging_iterator_walks_through_multiple_pages(): void
    {
        $http = new FakeHttpClient();
        $http
            ->queue(new Response(
                200,
                '{"data":['
                . '{"id":"' . self::ID_1 . '","object":"customer"},'
                . '{"id":"' . self::ID_2 . '","object":"customer"}'
                . '],'
                . '"links":{"next":"https://x/v1/customers?limit=2&starting_after=' . self::ID_2 . '"},'
                . '"meta":{"next_cursor":"' . self::ID_2 . '"}}',
                [],
            ))
            ->queue(new Response(
                200,
                '{"data":[{"id":"' . self::ID_3 . '","object":"customer"}],'
                . '"links":{"next":null},'
                . '"meta":{"next_cursor":null}}',
                [],
            ));

        $service = new CustomerService(new ApiRequestor($http, 'sk', 'https://x', '2025-10-02'));

        $collected = [];
        foreach ($service->all(['limit' => 2])->autoPagingIterator() as $c) {
            $this->assertInstanceOf(Customer::class, $c);
            $collected[] = $c->id;
        }

        $this->assertSame([self::ID_1, self::ID_2, self::ID_3], $collected);
        $this->assertSame('https://x/v1/customers?limit=2', $http->calls[0]['url']);
        $this->assertSame(
            'https://x/v1/customers?limit=2&starting_after=' . self::ID_2,
            $http->calls[1]['url'],
        );
    }

    #[Test]
    public function auto_paging_stops_when_links_next_is_null(): void
    {
        $http = new FakeHttpClient();
        $http->queue(new Response(
            200,
            '{"data":[{"id":"' . self::ID_1 . '","object":"customer"}],'
            . '"links":{"next":null},"meta":{}}',
            [],
        ));

        $service = new CustomerService(new ApiRequestor($http, 'sk', 'https://x', '2025-10-02'));

        $count = 0;
        foreach ($service->all()->autoPagingIterator() as $_) {
            $count++;
        }
        $this->assertSame(1, $count);
        $this->assertCount(1, $http->calls);
    }

    #[Test]
    public function fromList_constructs_a_collection_with_meta(): void
    {
        $list = Collection::fromList([
            'data' => [['id' => self::ID_1, 'object' => 'customer']],
            'links' => ['next' => 'https://x/page2'],
            'meta' => ['next_cursor' => self::ID_1, 'per_page' => 25],
        ]);

        $this->assertCount(1, $list->data());
        $this->assertTrue($list->hasMore());
        $this->assertSame(self::ID_1, $list->nextCursor());
        $this->assertSame(25, $list->meta['per_page']);
    }
}
