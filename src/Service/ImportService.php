<?php

declare(strict_types=1);

namespace Debi\Service;

use Debi\Collection;
use Debi\RequestOptions;
use Debi\Resource\Import;

/**
 * Operations on `/v1/imports`.
 *
 * Import payloads carry no `object` discriminator, so `all()`, `retrieve()`
 * and `create()` name {@see Import} explicitly for hydration instead of
 * relying on the map. `rows()` does not: an import's rows are not imports.
 */
final class ImportService extends AbstractService
{
    private const BASE = '/v1/imports';

    /**
     * @param array<int|string,mixed>                 $params
     * @param array<string,mixed>|RequestOptions|null $opts
     */
    public function all(array $params = [], array|RequestOptions|null $opts = null): Collection
    {
        return $this->requestCollection(self::BASE, $params, $opts, Import::class);
    }

    /**
     * @param array<int|string,mixed>                 $params
     * @param array<string,mixed>|RequestOptions|null $opts
     */
    public function retrieve(string $id, array $params = [], array|RequestOptions|null $opts = null): Import
    {
        /** @var Import $obj */
        $obj = $this->request('GET', self::BASE . '/' . $id, $params, $opts, Import::class);
        return $obj;
    }

    /**
     * @param array<int|string,mixed>                 $params
     * @param array<string,mixed>|RequestOptions|null $opts
     */
    public function create(array $params, array|RequestOptions|null $opts = null): Import
    {
        /** @var Import $obj */
        $obj = $this->request('POST', self::BASE, $params, $opts, Import::class);
        return $obj;
    }

    /**
     * @param array<int|string,mixed>                 $params
     * @param array<string,mixed>|RequestOptions|null $opts
     */
    public function rows(string $id, array $params = [], array|RequestOptions|null $opts = null): Collection
    {
        return $this->requestCollection(self::BASE . '/' . $id . '/rows', $params, $opts);
    }
}
