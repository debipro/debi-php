<?php

declare(strict_types=1);

namespace Debi\Resource;

use Debi\ApiResource;

/**
 * A customer of your organization.
 *
 * @property string      $id
 * @property string      $object
 * @property bool        $livemode
 * @property ?string     $name
 * @property ?string     $email
 * @property ?string     $mobile_number
 * @property ?string     $default_payment_method_id
 * @property ?string     $gateway_identifier
 * @property ?array      $metadata
 * @property int         $created_at
 * @property int         $updated_at
 */
final class Customer extends ApiResource
{
    public const OBJECT_NAME = 'customer';
}
