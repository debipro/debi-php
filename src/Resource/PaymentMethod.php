<?php

declare(strict_types=1);

namespace Debi\Resource;

use Debi\ApiResource;

/**
 * A customer's payment instrument (card, bank account, etc.).
 *
 * @property string  $id
 * @property string  $object
 * @property bool    $livemode
 * @property string  $type
 * @property ?string $customer_id
 * @property ?array  $metadata
 * @property int     $created_at
 */
final class PaymentMethod extends ApiResource
{
    public const OBJECT_NAME = 'payment_method';
}
