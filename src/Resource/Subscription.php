<?php

declare(strict_types=1);

namespace Debi\Resource;

use Debi\ApiResource;
use Debi\DebiObject;

/**
 * A recurring subscription schedule for a customer.
 *
 * Sample id: `SBmQ6j9NWxblNv`.
 *
 * The customer and the payment method arrive expanded, as full objects. There
 * is no `customer_id` on the wire, so reach for `$subscription->customer->id`.
 *
 * @property string         $id
 * @property string         $object
 * @property float          $amount
 * @property string         $description
 * @property string         $currency
 * @property string         $status                    one of `active`, `paused`, `cancelled`, `finished`, `incomplete`, `incomplete_expired`
 * @property ?int           $count                     total number of payments to collect, `null` when open-ended
 * @property string         $start_date
 * @property string         $interval_unit             one of `weekly`, `monthly`, `yearly`
 * @property int            $interval
 * @property int            $day_of_month              defaults to 1, so present even on weekly schedules
 * @property ?int           $day_of_week               `null` unless `interval_unit` is `weekly`
 * @property bool           $livemode
 * @property string         $created_at
 * @property string         $updated_at
 * @property string         $first_date
 * @property string[]       $upcoming_dates            up to 5 upcoming charge dates
 * @property Customer       $customer
 * @property PaymentMethod  $payment_method
 * @property ?int           $auto_retries_max_attempts
 * @property ?DebiObject    $metadata
 */
final class Subscription extends ApiResource
{
    public const OBJECT_NAME = 'subscription';
}
