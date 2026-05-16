<?php

declare(strict_types=1);

namespace Debi;

/**
 * A paginated list response (`{ "object": "list", "data": [...] }`).
 *
 * Iterable directly over the current page, or with {@see autoPagingIterator()}
 * across all pages using the API's cursor pagination (`starting_after`).
 *
 * @implements \IteratorAggregate<int, mixed>
 */
class Collection extends ApiResource implements \IteratorAggregate
{
    public const OBJECT_NAME = 'list';

    private ?ApiRequestor $requestor = null;
    private string $requestPath = '';
    /** @var array<int|string,mixed> */
    private array $requestParams = [];
    private ?RequestOptions $requestOpts = null;

    /**
     * Bind the parameters that produced this list so {@see autoPagingIterator()}
     * can transparently fetch subsequent pages. Called by services after each
     * list-style request — user code should not invoke this directly.
     *
     * @internal
     *
     * @param array<int|string,mixed> $params
     */
    public function setRequestParams(
        ApiRequestor $requestor,
        string $path,
        array $params,
        ?RequestOptions $opts,
    ): void {
        $this->requestor = $requestor;
        $this->requestPath = $path;
        $this->requestParams = $params;
        $this->requestOpts = $opts;
    }

    /**
     * @return array<int, mixed>
     */
    public function data(): array
    {
        $data = $this->values['data'] ?? [];
        return is_array($data) ? array_values($data) : [];
    }

    public function getIterator(): \Generator
    {
        foreach ($this->data() as $item) {
            yield $item;
        }
    }

    /**
     * Iterate over every item across all pages, transparently fetching the
     * next page when the current one is exhausted. Safe to interrupt; the
     * iterator keeps no resources beyond the next-page cursor.
     *
     * @return \Generator<int, mixed>
     */
    public function autoPagingIterator(): \Generator
    {
        $page = $this;
        while (true) {
            $items = $page->data();
            if ($items === []) {
                return;
            }
            $lastId = null;
            foreach ($items as $item) {
                yield $item;
                if ($item instanceof DebiObject) {
                    $candidate = $item['id'] ?? null;
                    if (is_string($candidate)) {
                        $lastId = $candidate;
                    }
                } elseif (is_array($item) && isset($item['id']) && is_string($item['id'])) {
                    $lastId = $item['id'];
                }
            }
            if ($lastId === null || $page->requestor === null) {
                return;
            }
            $page = $page->fetchNextPage($lastId);
            if ($page === null) {
                return;
            }
        }
    }

    private function fetchNextPage(string $lastId): ?self
    {
        if ($this->requestor === null) {
            return null;
        }
        $params = $this->requestParams;
        $params['starting_after'] = $lastId;
        unset($params['ending_before']);

        [$body] = $this->requestor->request('GET', $this->requestPath, $params, $this->requestOpts);

        $next = Util\Util::convertToObject($body);
        if (!$next instanceof self) {
            return null;
        }
        $next->setRequestParams($this->requestor, $this->requestPath, $params, $this->requestOpts);
        return $next;
    }
}
