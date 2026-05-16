<?php

declare(strict_types=1);

namespace Debi\Resource;

use Debi\ApiResource;

/**
 * A hosted page to process payments, subscriptions and mandates.
 *
 * @property string  $id
 * @property string  $object
 * @property bool    $livemode
 * @property string  $url
 * @property string  $status
 * @property ?string $customer_id
 * @property ?array  $metadata
 * @property int     $created_at
 * @property int     $expires_at
 */
final class Session extends ApiResource
{
    public const OBJECT_NAME = 'session';
}
