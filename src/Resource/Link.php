<?php

declare(strict_types=1);

namespace Debi\Resource;

use Debi\ApiResource;

/**
 * A shareable payment link.
 *
 * @property string  $id
 * @property string  $object
 * @property bool    $livemode
 * @property string  $url
 * @property string  $status
 * @property ?array  $metadata
 * @property int     $created_at
 */
final class Link extends ApiResource
{
    public const OBJECT_NAME = 'link';
}
