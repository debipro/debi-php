<?php

declare(strict_types=1);

namespace Debi\Service;

use Debi\Collection;
use Debi\RequestOptions;
use Debi\Resource\PaymentMethod;

/**
 * Operations on `/v1/payment_methods`.
 */
final class PaymentMethodService extends AbstractService
{
    private const BASE = '/v1/payment_methods';

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
    public function retrieve(string $id, array $params = [], array|RequestOptions|null $opts = null): PaymentMethod
    {
        /** @var PaymentMethod $obj */
        $obj = $this->request('GET', self::BASE . '/' . $id, $params, $opts);
        return $obj;
    }

    /**
     * @param array<int|string,mixed>                 $params
     * @param array<string,mixed>|RequestOptions|null $opts
     */
    public function create(array $params, array|RequestOptions|null $opts = null): PaymentMethod
    {
        /** @var PaymentMethod $obj */
        $obj = $this->request('POST', self::BASE, $params, $opts);
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
    public function attach(string $id, array|RequestOptions|null $opts = null): PaymentMethod
    {
        /** @var PaymentMethod $obj */
        $obj = $this->customAction('attach', self::BASE, $id, [], $opts);
        return $obj;
    }

    /**
     * @param array<string,mixed>|RequestOptions|null $opts
     */
    public function detach(string $id, array|RequestOptions|null $opts = null): PaymentMethod
    {
        /** @var PaymentMethod $obj */
        $obj = $this->customAction('detach', self::BASE, $id, [], $opts);
        return $obj;
    }
}
