# Changelog

All notable changes to `debi/debi-php` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

The whole surface has been reconciled against the published Debi OpenAPI
specification (version `2025-10-02`). Parts of the pre-1.0 SDK described an API
that did not exist: endpoints that were never served, and resource fields that
never appeared on the wire. Those are corrected here, and reading a field the
API did not return no longer fails silently.

Changes are grouped by topic below. The two that affect working code are
[Strict property access](#strict-property-access) and
[Resource fields](#resource-fields).

### Added

**Billing portal**
- `Debi\Resource\BillingPortalSession` + `Debi\Service\BillingPortalSessionService`,
  exposed as `$debi->billingPortalSessions`. Wraps `POST /v1/billing_portal/sessions`
  for redirecting customers into Debi's hosted billing portal.
- `Debi\Resource\BillingPortalConfiguration` + `Debi\Service\BillingPortalConfigurationService`,
  exposed as `$debi->billingPortalConfigurations`. Wraps the configurations
  CRUD surface (`GET/POST /v1/billing_portal/configurations` and
  `GET/PUT /v1/billing_portal/configurations/{id}`).

**Payment reconciliation (BETA)**
- `PaymentService::transaction()`, `transactionMatchCandidates()`, and
  `transactionMatch()` for the payment ↔ bank-transaction reconciliation
  endpoints. Marked BETA in the SDK as well as in the spec; subject to
  breaking change without notice.

**Internals and examples**
- `AbstractService::subResource()` helper for sub-resource paths that do not
  use the `/actions/{verb}` convention (e.g. `payment_methods/{id}/attach`).
- `examples/sandbox.php` — a runnable live-sandbox smoke test script that
  exercises customers, payment methods, billing portal, payments and events
  against `https://api.debi-test.pro` with a real `DEBI_API_KEY`.

### Changed (breaking, pre-1.0)

#### Strict property access

Reading a property the response did not contain now raises an `E_USER_WARNING`
and evaluates to null. Previously it returned null in silence, which made a
misspelled or renamed field indistinguishable from one the API legitimately
returned as null — the mistake surfaced far from its cause, or never surfaced
at all. A field the API did send as null still reads as null, quietly.

Absence is sometimes a legitimate answer rather than a mistake — probing for a
field a newer API version returns but the installed SDK does not document yet,
for instance. Three ways to read without tripping the warning:

```php
$object['new_field'];            // array access
$object->new_field ?? $default;  // `??` consults __isset() first
isset($object->new_field);
```

Note for applications that promote warnings to exceptions (Laravel's default
error handler does): a plain `$object->missing_field` throws there rather than
evaluating to null, which makes the three forms above load-bearing.

#### Billing portal

- **Discriminator names** now use the dotted form the API actually returns:
  `billing_portal.session` and `billing_portal.configuration` (the SDK
  previously guessed `billing_portal_session`). Hydration of these objects
  worked only against fabricated fixtures before; it now works against real
  API responses.
- The `BillingPortalSession` resource gained `billing_portal_configuration_id`
  and `updated_at`; the previously-imagined `expires_at` was removed.

#### Payment method attach / detach

- **`PaymentMethodService::attach()`** now requires the customer id as its
  second argument and POSTs to `/v1/payment_methods/{id}/attach` (the real
  path) rather than the previously fabricated
  `/v1/payment_methods/{id}/actions/attach`. It returns an empty `DebiObject`
  (the API responds with 204 No Content).
- **`PaymentMethodService::detach()`** now POSTs to
  `/v1/payment_methods/{id}/detach` (was `/actions/detach`). Returns an empty
  `DebiObject`.

### Removed (breaking, pre-1.0)

- `BillingPortalSessionService::retrieve()`. The Debi API has no
  `GET /v1/billing_portal/sessions/{id}` endpoint; the previous implementation
  only worked against stubbed responses. A regression test now guards against
  re-introducing it.

### Fixed

#### Resource fields

Every resource's `@property` documentation has been reconciled against the
serializers the API actually uses: fields that never existed on the wire were
dropped, and missing ones filled in. Combined with
[strict property access](#strict-property-access), reads that used to return a
silent null are now visible. The corrections that change what working code
should read:

- `Payment`, `Subscription` and `Mandate` documented a `customer_id` (and a
  `payment_method_id` / `mandate_id`). None of them exist: the customer and the
  payment method arrive **expanded**, so the id lives at
  `$payment->customer->id`.
- `Payment` gained `subscription`, `gateway` and `session`, which arrive as
  bare id strings rather than expanded objects — the opposite convention from
  `customer` on the same resource.
- `Session` documented `url`, `status`, `metadata` and `expires_at`. The hosted
  page is at `public_uri`, and a finished session is one with a `completed_at`.
- `Gateway` documented `name` and `enabled`; the API sends `provider` and the
  inverted `disabled`.
- `PaymentMethod` gained the `mercadopago` instrument type alongside `card`,
  `sepa_debit`, `cbu`, `cvu` and `transfer`. It is newer than the published
  OpenAPI specification, which still lists only the other five.
- `Refund` documented a `livemode` it never returns. `Export` and `Import`
  documented `object` and `resource` fields they never return.
- JSON objects such as `metadata`, `next_action` and `batch_job` hydrate into
  `DebiObject`, not `array`, and are typed as such.
- Nullability now follows the spec where the spec is explicit about it.
  `Subscription::$description`, `$start_date`, `$first_date` and `$day_of_month`
  and `Export::$filename` and `$created_from` were documented as nullable but
  are always present and never null, so callers no longer need to guard them.
  `Subscription::$day_of_month` in particular defaults to `1` and is present
  even on weekly schedules; `$day_of_week` is the one that goes null.

#### Response hydration

- `ExportService` and `ImportService` raised a `TypeError` on every successful
  call: their responses carry no `object` discriminator, so hydration fell back
  to a plain `DebiObject`, which the declared `: Export` / `: Import` return
  types reject. Both services now name the class to hydrate into, and their
  list endpoints yield `Export` / `Import` items rather than bare `DebiObject`s.
  The bug was invisible because the tests stubbed a discriminator the API does
  not send; they now stub the real shape.
- `LinkService::delete()` and `WebhookEndpointService::delete()` no longer
  throw `UnexpectedValueException` on a 204 (No Content) response. The shared
  `AbstractService::request()` now tolerates an empty body and returns an empty
  `DebiObject`, so `delete()` correctly returns void.

#### Test fidelity

- The end-to-end test stubbed a payment response with a `customer_id` scalar,
  enshrining a shape the API never returns. It now stubs, and asserts, the
  expanded customer.
- Service search tests now use the real `q` query parameter documented in the
  spec instead of the made-up `query` parameter.
- The suite fails on unexpected `E_USER_WARNING`s, so an SDK-internal read of a
  field the API does not return can no longer pass CI unnoticed.

## [0.1.0] - 2026-05-16

### Added
- First public release of the Debi PHP SDK.
- `Debi\DebiClient` entry point with lazily-instantiated resource services
  for customers, payments, subscriptions, mandates, payment methods, refunds,
  sessions, links, events, exports, imports, gateways, and webhook endpoints.
- Typed exception hierarchy (`Debi\Exception\*`) keyed off HTTP status, plus
  a shared `ExceptionInterface` marker for catch-all handlers.
- `Debi\Webhook` signature verifier (HMAC-SHA256, timestamp tolerance,
  multi-signature support for secret rotation, constant-time comparison).
- `Debi\HttpClient\DefaultClient` PSR-18 transport with safe retry policy:
  automatic retries on transport errors, HTTP 429, and HTTP 5xx for idempotent
  methods (POST is retried only when an `Idempotency-Key` header is present),
  honours server-supplied `Retry-After`, exponential backoff with jitter.
- Cursor-based `Debi\Collection` with `autoPagingIterator()` for transparent
  pagination across pages.
- `Debi\RequestOptions` for per-request overrides (idempotency key, API key,
  API version, custom headers) without breaking existing call sites.

[Unreleased]: https://github.com/debipro/debi-php/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/debipro/debi-php/releases/tag/v0.1.0
