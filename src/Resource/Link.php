<?php

declare(strict_types=1);

namespace Debi\Resource;

use Debi\ApiResource;
use Debi\DebiObject;

/**
 * A shareable payment link.
 *
 * Sample id: `LKYeoQ4WbDe9xdRq7j`.
 *
 * @property string      $id
 * @property string      $uuid
 * @property string      $object
 * @property string      $title
 * @property ?string     $body
 * @property ?string     $button_text
 * @property ?string     $name_text
 * @property ?string     $success_url
 * @property string      $kind                      one of `payment`, `subscription`, `mandate`
 * @property ?DebiObject $metadata
 * @property ?array      $extra_fields
 * @property ?array      $extra_fields_customer
 * @property ?DebiObject $options
 * @property string      $created_at
 * @property string      $updated_at
 * @property bool        $livemode
 * @property ?bool       $enabled
 * @property ?bool       $smart_merge
 * @property ?bool       $binary_mode
 * @property ?string[]   $payment_method_types
 * @property ?DebiObject $payment_method_options
 * @property DebiObject  $supported_payment_methods
 * @property string      $public_uri                the shareable URL
 * @property DebiObject  $user                      branding of the organization that owns the link
 */
final class Link extends ApiResource
{
    public const OBJECT_NAME = 'link';
}
