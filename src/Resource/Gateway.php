<?php

declare(strict_types=1);

namespace Debi\Resource;

use Debi\ApiResource;

/**
 * A configured upstream payment gateway.
 *
 * @property string $id
 * @property string $object
 * @property string $name
 * @property string $status
 * @property bool   $enabled
 */
final class Gateway extends ApiResource
{
    public const OBJECT_NAME = 'gateway';
}
