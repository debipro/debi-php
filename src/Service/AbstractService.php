<?php

declare(strict_types=1);

namespace Debi\Service;

use Debi\ApiRequestor;
use Debi\Collection;
use Debi\DebiObject;
use Debi\RequestOptions;
use Debi\Util\Util;

/**
 * Base class for all resource services. Provides the small set of helpers
 * every service needs (`request`, `requestCollection`, `customAction`) so
 * that concrete services contain only path strings and method signatures.
 */
abstract class AbstractService
{
    public function __construct(protected readonly ApiRequestor $requestor) {}

    /**
     * @param array<int|string,mixed>          $params
     * @param array<string,mixed>|RequestOptions|null $opts
     */
    protected function request(
        string $method,
        string $path,
        array $params = [],
        array|RequestOptions|null $opts = null,
    ): DebiObject {
        $options = RequestOptions::parse($opts);
        [$body] = $this->requestor->request($method, $path, $params, $options);

        $result = Util::convertToObject($body);
        if (!$result instanceof DebiObject) {
            throw new \UnexpectedValueException('Debi API returned a non-object response.');
        }
        return $result;
    }

    /**
     * @param array<int|string,mixed>          $params
     * @param array<string,mixed>|RequestOptions|null $opts
     */
    protected function requestCollection(
        string $path,
        array $params = [],
        array|RequestOptions|null $opts = null,
    ): Collection {
        $options = RequestOptions::parse($opts);
        [$body] = $this->requestor->request('GET', $path, $params, $options);

        $result = Util::convertToObject($body);
        if (!$result instanceof Collection) {
            throw new \UnexpectedValueException(
                'Expected a list response from ' . $path . ' but received a single object.'
            );
        }
        $result->setRequestParams($this->requestor, $path, $params, $options);
        return $result;
    }

    /**
     * Invoke an `actions/{verb}` endpoint such as `/v1/payments/{id}/actions/cancel`.
     * Centralizing this avoids a one-off method-per-action in every service.
     *
     * @param array<int|string,mixed>          $params
     * @param array<string,mixed>|RequestOptions|null $opts
     */
    protected function customAction(
        string $verb,
        string $basePath,
        string $id,
        array $params = [],
        array|RequestOptions|null $opts = null,
    ): DebiObject {
        return $this->request('POST', "{$basePath}/{$id}/actions/{$verb}", $params, $opts);
    }
}
