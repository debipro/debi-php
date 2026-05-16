<?php

declare(strict_types=1);

namespace Debi\Resource;

use Debi\ApiResource;

/**
 * A payment charged to a customer's payment method.
 *
 * @property string  $id
 * @property string  $object
 * @property bool    $livemode
 * @property int     $amount
 * @property string  $currency
 * @property string  $status
 * @property ?string $customer_id
 * @property ?string $payment_method_id
 * @property ?string $mandate_id
 * @property ?array  $metadata
 * @property int     $created_at
 */
final class Payment extends ApiResource
{
    public const OBJECT_NAME = 'payment';
}
