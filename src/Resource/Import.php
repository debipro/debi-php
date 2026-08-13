<?php

declare(strict_types=1);

namespace Debi\Resource;

use Debi\ApiResource;
use Debi\DebiObject;

/**
 * A bulk import job.
 *
 * Sample id: `IMB1rRDqkM5X`.
 *
 * Import payloads carry no `object` discriminator, unlike most of the API, so
 * {@see \Debi\Service\ImportService} names this class explicitly when hydrating
 * rather than relying on the discriminator map.
 *
 * @property string      $id
 * @property string      $type               the resource being imported, e.g. `customers`
 * @property string      $status
 * @property ?string     $original_filename
 * @property ?bool       $smart_merge
 * @property bool        $livemode
 * @property ?int        $rows_count
 * @property ?int        $valid_rows_count
 * @property ?int        $invalid_rows_count
 * @property ?DebiObject $batch_job          progress counters, only while the job is running
 * @property string      $created_at
 * @property string      $updated_at
 * @property ?string     $ready_at
 * @property ?string     $cancelled_at
 * @property ?string     $processed_at
 * @property ?string     $invalid_at
 */
final class Import extends ApiResource
{
    public const OBJECT_NAME = 'import';
}
