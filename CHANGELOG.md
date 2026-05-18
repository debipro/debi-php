# Changelog

All notable changes to `debi/debi-php` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

The whole surface has been reconciled against the published Debi OpenAPI
specification (`openapi/openapi.yaml`, version `2025-10-02`). Several pre-1.0
endpoints were renamed, removed, or added to match the spec exactly.

### Added
- `Debi\Resource\BillingPortalSession` + `Debi\Service\BillingPortalSessionService`,
  exposed as `$debi->billingPortalSessions`. Wraps `POST /v1/billing_portal/sessions`
  for redirecting customers into Debi's hosted billing portal.
- `Debi\Resource\BillingPortalConfiguration` + `Debi\Service\BillingPortalConfigurationService`,
  exposed as `$debi->billingPortalConfigurations`. Wraps the configurations
  CRUD surface (`GET/POST /v1/billing_portal/configurations` and
  `GET/PUT /v1/billing_portal/configurations/{id}`).
- `PaymentService::transaction()`, `transactionMatchCandidates()`, and
  `transactionMatch()` for the BETA payment ↔ bank-transaction reconciliation
  endpoints. Marked BETA in the SDK as well as in the spec.
- `AbstractService::subResource()` helper for sub-resource paths that do not
  use the `/actions/{verb}` convention (e.g. `payment_methods/{id}/attach`).
- `examples/sandbox.php` — a runnable live-sandbox smoke test script that
  exercises customers, payment methods, billing portal, payments and events
  against `https://api.debi-test.pro` with a real `DEBI_API_KEY`.

### Changed (breaking, pre-1.0)
- **Discriminator names** for billing-portal resources now use the dotted form
  the API actually returns: `billing_portal.session` and
  `billing_portal.configuration` (previously the SDK guessed
  `billing_portal_session`). Hydration of these objects worked only against
  fabricated fixtures before; it now works against real API responses.
- **`PaymentMethodService::attach()`** now requires the customer id as its
  second argument and POSTs to `/v1/payment_methods/{id}/attach` (the real
  path) rather than the previously fabricated `/v1/payment_methods/{id}/actions/attach`.
  It returns an empty `DebiObject` (the API responds with 204 No Content).
- **`PaymentMethodService::detach()`** now POSTs to
  `/v1/payment_methods/{id}/detach` (was `/actions/detach`). Returns an empty
  `DebiObject`.
- The `BillingPortalSession` resource gained `billing_portal_configuration_id`
  and `updated_at` properties; the previously-imagined `expires_at` was
  removed (the API does not expose it).

### Removed (breaking, pre-1.0)
- `BillingPortalSessionService::retrieve()` has been removed. The Debi API
  has no `GET /v1/billing_portal/sessions/{id}` endpoint; the previous
  implementation only worked against stubbed responses. A regression test now
  guards against re-introducing it.

### Fixed
- `LinkService::delete()` and `WebhookEndpointService::delete()` no longer
  throw `UnexpectedValueException` on a 204 (No Content) response. The
  shared `AbstractService::request()` now tolerates an empty body and
  returns an empty `DebiObject`, so `delete()` correctly returns void.
- Service search tests now use the real `q` query parameter documented in
  the spec instead of the made-up `query` parameter.

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

[Unreleased]: https://github.com/debi/debi-php/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/debi/debi-php/releases/tag/v0.1.0
