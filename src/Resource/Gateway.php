<?php

declare(strict_types=1);

namespace Debi\Resource;

use Debi\ApiResource;
use Debi\DebiObject;

/**
 * A configured upstream payment gateway.
 *
 * Sample id: `GWBZqKYEK7Y2`.
 *
 * Availability is expressed negatively, as `disabled`; there is no `enabled`
 * field to read.
 *
 * @property string      $id
 * @property string      $object
 * @property string      $provider
 * @property ?string     $number
 * @property ?string     $username
 * @property ?int        $number_bank_retries
 * @property ?int        $code_length
 * @property bool        $disabled
 * @property bool        $livemode
 * @property string      $created_at
 * @property ?string     $updated_at
 * @property ?string     $approved_at
 * @property DebiObject  $supported_payment_methods
 */
final class Gateway extends ApiResource
{
    public const OBJECT_NAME = 'gateway';
}
