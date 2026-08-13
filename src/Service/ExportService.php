<?php

declare(strict_types=1);

namespace Debi\Service;

use Debi\Collection;
use Debi\RequestOptions;
use Debi\Resource\Export;

/**
 * Operations on `/v1/exports`.
 *
 * Export payloads carry no `object` discriminator, so every call here names
 * {@see Export} explicitly for hydration instead of relying on the map.
 */
final class ExportService extends AbstractService
{
    private const BASE = '/v1/exports';

    /**
     * @param array<int|string,mixed>                 $params
     * @param array<string,mixed>|RequestOptions|null $opts
     */
    public function all(array $params = [], array|RequestOptions|null $opts = null): Collection
    {
        return $this->requestCollection(self::BASE, $params, $opts, Export::class);
    }

    /**
     * @param array<int|string,mixed>                 $params
     * @param array<string,mixed>|RequestOptions|null $opts
     */
    public function retrieve(string $id, array $params = [], array|RequestOptions|null $opts = null): Export
    {
        /** @var Export $obj */
        $obj = $this->request('GET', self::BASE . '/' . $id, $params, $opts, Export::class);
        return $obj;
    }

    /**
     * @param array<int|string,mixed>                 $params
     * @param array<string,mixed>|RequestOptions|null $opts
     */
    public function create(array $params, array|RequestOptions|null $opts = null): Export
    {
        /** @var Export $obj */
        $obj = $this->request('POST', self::BASE, $params, $opts, Export::class);
        return $obj;
    }
}
