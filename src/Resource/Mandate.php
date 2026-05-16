<?php

declare(strict_types=1);

namespace Debi\Resource;

use Debi\ApiResource;

/**
 * A customer's authorization to debit a payment method.
 *
 * @property string  $id
 * @property string  $object
 * @property bool    $livemode
 * @property string  $status
 * @property string  $customer_id
 * @property ?string $payment_method_id
 * @property ?array  $metadata
 * @property int     $created_at
 */
final class Mandate extends ApiResource
{
    public const OBJECT_NAME = 'mandate';
}
