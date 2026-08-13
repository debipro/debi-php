<?php

declare(strict_types=1);

namespace Debi\Resource;

use Debi\ApiResource;
use Debi\DebiObject;

/**
 * A customer's authorization to debit a payment method.
 *
 * Sample id: `MAmQ6j9NWxblNv`.
 *
 * The customer and the payment method arrive expanded, as full objects, so
 * reach for `$mandate->customer->id` rather than a `customer_id` field.
 *
 * @property string         $id
 * @property string         $uuid
 * @property string         $object
 * @property bool           $livemode
 * @property string         $status
 * @property Customer       $customer
 * @property PaymentMethod  $payment_method
 * @property ?DebiObject    $metadata
 * @property string         $created_at
 * @property string         $updated_at
 * @property ?string        $deleted_at
 */
final class Mandate extends ApiResource
{
    public const OBJECT_NAME = 'mandate';
}
