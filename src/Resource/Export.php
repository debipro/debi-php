<?php

declare(strict_types=1);

namespace Debi\Resource;

use Debi\ApiResource;

/**
 * A report export job (CSV / Excel).
 *
 * Export payloads carry no `object` discriminator, unlike most of the API, so
 * {@see \Debi\Service\ExportService} names this class explicitly when hydrating
 * rather than relying on the discriminator map.
 *
 * @property string  $id
 * @property string  $type         what is being exported, e.g. `payments_monthly`, `customers`
 * @property string  $filename     generated from the type and creation date when not supplied
 * @property string  $status       one of `pending`, `ready`, `failed`, `skipped`
 * @property string  $created_from `manual` for API/dashboard exports, `schedule` for reports
 * @property bool    $livemode
 * @property string  $created_at
 * @property string  $updated_at
 */
final class Export extends ApiResource
{
    public const OBJECT_NAME = 'export';
}
