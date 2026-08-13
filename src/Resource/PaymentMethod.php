<?php

declare(strict_types=1);

namespace Debi\Resource;

use Debi\ApiResource;
use Debi\DebiObject;

/**
 * A customer's payment instrument (card, bank account, CBU, etc.).
 *
 * Sample id: `PMJODBMZdayP`.
 *
 * Exactly one of the instrument sub-objects is present, the one named by
 * `type`: a card payment method carries `card` and nothing else.
 *
 * `mercadopago` is newer than the published OpenAPI specification, which still
 * lists only the five types before it. It is documented here because the API
 * returns it; expect the spec to catch up.
 *
 * @property string      $id
 * @property string      $object
 * @property string      $type        one of `card`, `sepa_debit`, `cbu`, `cvu`, `transfer`, `mercadopago`
 * @property ?DebiObject $card
 * @property ?DebiObject $cbu
 * @property ?DebiObject $cvu
 * @property ?DebiObject $sepa_debit
 * @property ?DebiObject $transfer
 * @property ?DebiObject $mercadopago
 * @property bool        $livemode
 * @property ?DebiObject $metadata
 * @property ?string     $customer_id
 * @property string      $created_at
 * @property string      $updated_at
 */
final class PaymentMethod extends ApiResource
{
    public const OBJECT_NAME = 'payment_method';
}
