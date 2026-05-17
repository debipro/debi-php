# Security Policy

We take the security of `debi/debi-php` and the systems that integrate with the
Debi API seriously. Thank you for helping keep our users and their data safe.

## Reporting a Vulnerability

**Please do not report security vulnerabilities through public GitHub issues,
pull requests, or discussions.**

Instead, email <system@debi.pro> with:

- A description of the vulnerability and its impact.
- Steps to reproduce (proof-of-concept code is appreciated).
- The affected version(s) of the SDK.
- Any suggested remediation, if you have one.

You will receive an acknowledgement within **3 business days**, and we will
keep you informed as the report is triaged and a fix is prepared.

We support coordinated disclosure: please give us a reasonable window
(typically 90 days) to issue a patched release before publishing details.

## Supported Versions

Only the latest minor release receives security fixes. While the library is
pre-1.0, security fixes are released as new patch versions of the most recent
minor (e.g. `0.1.x`).

| Version | Supported          |
| ------- | ------------------ |
| 0.1.x   | :white_check_mark: |

## Scope

In scope:

- Code in `src/` (the public SDK surface).
- Signed-webhook handling (`Debi\Webhook`).
- HTTP transport defaults (`Debi\HttpClient\DefaultClient`).

Out of scope:

- Bugs in PSR-18 client libraries (Guzzle, Symfony HttpClient, etc.).
  Report those upstream.
- Issues only reproducible by code in `examples/` — those are illustrative
  scripts, not part of the published library surface.
- The Debi REST API itself: please contact <system@debi.pro> directly with
  API-side findings.

## Hardening Recommendations for Integrators

- Store your secret API key outside source control (env vars, secrets manager).
- Always verify webhook signatures with `Debi\Webhook::constructEvent()` and
  pass the **raw, unmodified** request body — never a re-serialized version.
- Rotate webhook signing secrets periodically. Debi supports overlap by
  delivering with multiple `v1=` signatures during rotation; both old and
  new endpoints will continue to verify.
- Treat any `SignatureVerificationException` as a hostile request: drop the
  payload and respond with a 4xx, without echoing details to the caller.
