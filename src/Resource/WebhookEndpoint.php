<?php

declare(strict_types=1);

namespace Debi\Resource;

use Debi\ApiResource;

/**
 * A registered webhook delivery endpoint.
 *
 * @property string         $id
 * @property string         $object
 * @property string         $url
 * @property string         $status
 * @property array<string>  $enabled_events
 * @property ?string        $secret
 * @property int            $created_at
 */
final class WebhookEndpoint extends ApiResource
{
    public const OBJECT_NAME = 'webhook_endpoint';
}
