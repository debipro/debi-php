<?php

declare(strict_types=1);

namespace Debi\Resource;

use Debi\ApiResource;

/**
 * A recurring subscription schedule for a customer.
 *
 * @property string  $id
 * @property string  $object
 * @property bool    $livemode
 * @property string  $status
 * @property string  $customer_id
 * @property ?string $mandate_id
 * @property ?array  $metadata
 * @property int     $created_at
 */
final class Subscription extends ApiResource
{
    public const OBJECT_NAME = 'subscription';
}
