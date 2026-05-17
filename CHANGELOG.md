# Changelog

All notable changes to `debi/debi-php` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
