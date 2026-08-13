<?php

declare(strict_types=1);

namespace Debi\Resource;

use Debi\ApiResource;
use Debi\DebiObject;

/**
 * A snapshot of a state change inside Debi, delivered via webhooks or polled
 * from `/v1/events`. The `data.object` property carries the affected resource
 * at the time of the event.
 *
 * Sample id: `EV1rRDBDOEJM`.
 *
 * @property string      $id
 * @property string      $object
 * @property string      $created_at
 * @property DebiObject  $data
 * @property ?string     $delivered_at
 * @property bool        $livemode
 * @property string      $resource     the kind of resource the event is about, e.g. `payment`
 * @property ?string     $resource_id
 * @property string      $type
 * @property ?string     $request_id
 * @property ?string     $source
 */
final class Event extends ApiResource
{
    public const OBJECT_NAME = 'event';
}
