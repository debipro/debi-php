<?php

declare(strict_types=1);

namespace Debi\Service;

use Debi\Collection;
use Debi\RequestOptions;
use Debi\Resource\Payment;

/**
 * Operations on `/v1/payments`.
 */
final class PaymentService extends AbstractService
{
    private const BASE = '/v1/payments';

    /**
     * @param array<int|string,mixed>                 $params
     * @param array<string,mixed>|RequestOptions|null $opts
     */
    public function all(array $params = [], array|RequestOptions|null $opts = null): Collection
    {
        return $this->requestCollection(self::BASE, $params, $opts);
    }

    /**
     * @param array<int|string,mixed>                 $params
     * @param array<string,mixed>|RequestOptions|null $opts
     */
    public function retrieve(string $id, array $params = [], array|RequestOptions|null $opts = null): Payment
    {
        /** @var Payment $obj */
        $obj = $this->request('GET', self::BASE . '/' . $id, $params, $opts);
        return $obj;
    }

    /**
     * @param array<int|string,mixed>                 $params
     * @param array<string,mixed>|RequestOptions|null $opts
     */
    public function create(array $params, array|RequestOptions|null $opts = null): Payment
    {
        /** @var Payment $obj */
        $obj = $this->request('POST', self::BASE, $params, $opts);
        return $obj;
    }

    /**
     * @param array<int|string,mixed>                 $params
     * @param array<string,mixed>|RequestOptions|null $opts
     */
    public function update(string $id, array $params, array|RequestOptions|null $opts = null): Payment
    {
        /** @var Payment $obj */
        $obj = $this->request('PUT', self::BASE . '/' . $id, $params, $opts);
        return $obj;
    }

    /**
     * @param array<int|string,mixed>                 $params
     * @param array<string,mixed>|RequestOptions|null $opts
     */
    public function search(array $params, array|RequestOptions|null $opts = null): Collection
    {
        return $this->requestCollection(self::BASE . '/search', $params, $opts);
    }

    /**
     * @param array<string,mixed>|RequestOptions|null $opts
     */
    public function confirm(string $id, array|RequestOptions|null $opts = null): Payment
    {
        /** @var Payment $obj */
        $obj = $this->customAction('confirm', self::BASE, $id, [], $opts);
        return $obj;
    }

    /**
     * @param array<string,mixed>|RequestOptions|null $opts
     */
    public function cancel(string $id, array|RequestOptions|null $opts = null): Payment
    {
        /** @var Payment $obj */
        $obj = $this->customAction('cancel', self::BASE, $id, [], $opts);
        return $obj;
    }

    /**
     * @param array<string,mixed>|RequestOptions|null $opts
     */
    public function retry(string $id, array|RequestOptions|null $opts = null): Payment
    {
        /** @var Payment $obj */
        $obj = $this->customAction('retry', self::BASE, $id, [], $opts);
        return $obj;
    }

    /**
     * @param array<string,mixed>|RequestOptions|null $opts
     */
    public function stopAutoRetrying(string $id, array|RequestOptions|null $opts = null): Payment
    {
        /** @var Payment $obj */
        $obj = $this->customAction('stop_auto_retrying', self::BASE, $id, [], $opts);
        return $obj;
    }
}
