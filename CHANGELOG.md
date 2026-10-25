# Changelog

All notable changes to `kodvalley/improj-php` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.1.0] - 2026-10-25

### Added

- `Improj` client for the IMPROJ API v1 (API version `2026-10-24`) on PSR-18 and PSR-17, with optional `php-http/discovery`: me, projects, work packages, activities, tasks, milestones, members, files, calendar events, your calendar, meetings, webhook endpoints, AI agents, the OpenAPI document, and `request()` for endpoints not wrapped yet.
- Typed, read-only models with `toArray()` for forward compatibility.
- `Page` with `nextPage()` and lazy `autoPaging()` generators (`all()` on every list), protected against repeated cursors.
- Automatic `Idempotency-Key` on every POST (`RequestOptions` to override).
- Retries with exponential backoff and jitter, honouring `Retry-After`: `429` for any request; `503` and network errors for `GET`, `DELETE` and keyed `POST`.
- `ImprojException` hierarchy for 400, 401, 403, 404, 405, 409, 413, 415, 422, 429, 5xx and 503, with `getErrorCode()`, `getStatus()`, `getRequestId()`, `getErrors()` and `getRetryAfter()`; redacted messages. `ErrorCode` lists every documented code.
- `RequestOptions` `requestId` (sent as `X-Request-Id`, echoed by the API) and `apiVersion` (`IMPROJ-API-Version` for one request).
- Typed `Agent`, `AgentItem` and `AgentNote` models; agent items follow the signed `next_cursor` and fall back to the legacy `nextCursor`.
- Two-step upload helpers `upload()` and `uploadFile()`; the bytes always go to `<baseUrl>/uploads/<id>`.
- `WebhookVerifier` (`hash_hmac`, `hash_equals`, 300 second tolerance, several `v1` values) and examples for plain PHP and Laravel.
- Safety checks: no token over plain http, no redirects, masked token in dumps, non-serialisable client, `#[\SensitiveParameter]`.

[Unreleased]: https://github.com/kodvalley/improj-php/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/kodvalley/improj-php/releases/tag/v0.1.0
