<?php

declare(strict_types=1);

namespace Debi\Resource;

use Debi\ApiResource;

/**
 * A refund of a previously created payment.
 *
 * @property string  $id
 * @property string  $object
 * @property bool    $livemode
 * @property int     $amount
 * @property string  $currency
 * @property string  $status
 * @property string  $payment_id
 * @property ?string $reason
 * @property ?array  $metadata
 * @property int     $created_at
 */
final class Refund extends ApiResource
{
    public const OBJECT_NAME = 'refund';
}
