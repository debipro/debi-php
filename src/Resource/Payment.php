<?php

declare(strict_types=1);

namespace Debi\Resource;

use Debi\ApiResource;
use Debi\DebiObject;

/**
 * A payment charged to a customer's payment method.
 *
 * Sample id: `PY8EJ1NdNwzD`.
 *
 * The customer and the payment method arrive expanded, as full objects, so
 * reach for `$payment->customer->id`. The subscription, gateway and session
 * are the opposite: they arrive as bare id strings, never expanded.
 *
 * @property string        $id
 * @property string        $object
 * @property float         $amount
 * @property float         $amount_refunded
 * @property float         $amount_refundable
 * @property string        $currency
 * @property ?string       $description
 * @property string        $status
 * @property ?string       $response_message
 * @property ?string       $rejection_code
 * @property ?string       $provider_rejection_code
 * @property bool          $paid
 * @property bool          $retryable
 * @property bool          $refundable
 * @property bool          $livemode
 * @property string        $created_at
 * @property ?string       $charge_date
 * @property ?int          $submissions_count
 * @property ?string       $can_auto_retry_until
 * @property ?int          $auto_retries_max_attempts
 * @property ?string       $effective_charged_date
 * @property ?string       $estimated_accreditation_date
 * @property string        $updated_at
 * @property ?string       $updated_status
 * @property Customer      $customer
 * @property ?string       $subscription                 id of the Subscription that generated this payment
 * @property ?string       $subscription_payment_number
 * @property ?string       $gateway                      id of the Gateway that processed this payment
 * @property ?string       $session                      id of the Session that created this payment
 * @property PaymentMethod $payment_method
 * @property string        $gateway_identifier
 * @property ?bool         $binary_mode
 * @property ?DebiObject   $metadata
 * @property ?DebiObject   $next_action
 * @property ?string       $recovery_link
 * @property DebiObject[]  $logs
 * @property Refund[]      $refunds
 */
final class Payment extends ApiResource
{
    public const OBJECT_NAME = 'payment';
}
