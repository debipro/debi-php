<?php

declare(strict_types=1);

namespace Debi\Resource;

use Debi\ApiResource;
use Debi\DebiObject;

/**
 * A refund of a previously created payment.
 *
 * Sample id: `RFljikas9Fa8`.
 *
 * @property string      $id
 * @property string      $object
 * @property string      $payment_id
 * @property float       $amount
 * @property string      $currency
 * @property ?string     $reason
 * @property string      $status
 * @property string      $created_at
 * @property string      $updated_at
 * @property ?DebiObject $metadata
 */
final class Refund extends ApiResource
{
    public const OBJECT_NAME = 'refund';
}
