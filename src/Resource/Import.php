<?php

declare(strict_types=1);

namespace Debi\Resource;

use Debi\ApiResource;

/**
 * A bulk import job.
 *
 * @property string $id
 * @property string $object
 * @property string $status
 * @property string $resource
 * @property int    $created_at
 */
final class Import extends ApiResource
{
    public const OBJECT_NAME = 'import';
}
