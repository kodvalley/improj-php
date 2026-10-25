# kodvalley/improj-php

[![CI](https://github.com/kodvalley/improj-php/actions/workflows/ci.yml/badge.svg)](https://github.com/kodvalley/improj-php/actions/workflows/ci.yml)
[![Packagist](https://img.shields.io/packagist/v/kodvalley/improj-php)](https://packagist.org/packages/kodvalley/improj-php)
[![PHP](https://img.shields.io/badge/php-8.1%20to%208.5-777bb4)](composer.json)
[![PHPStan level max](https://img.shields.io/badge/PHPStan-level%20max-brightgreen)](phpstan.neon.dist)
[![OpenSSF Scorecard](https://api.scorecard.dev/projects/github.com/kodvalley/improj-php/badge)](https://scorecard.dev/viewer/?uri=github.com/kodvalley/improj-php)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue)](LICENSE)

The official PHP SDK for the [IMPROJ public API](https://improj.com/developers): typed resources, cursor pagination with generators, automatic idempotency keys, safe retries that honour `Retry-After`, the two-step upload helper and webhook verification.

- **PHP 8.1 to 8.5**, PSR-4, **PSR-18** HTTP client and **PSR-17** factories: use Guzzle, Symfony HttpClient or any other implementation.
- **Runtime dependencies: only the three PSR interface packages.** `php-http/discovery` is optional.
- PHPStan level max with strict rules, Psalm level 1 with taint analysis, PHP-CS-Fixer (PSR-12, PER-CS 2.0), PHPUnit with a 90% line-coverage gate.

## Install

```sh
composer require kodvalley/improj-php guzzlehttp/guzzle
```

Any PSR-18 client works. With Symfony: `composer require kodvalley/improj-php symfony/http-client nyholm/psr7`.

## Quick start

```php
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Kodvalley\Improj\Improj;

$factory = new HttpFactory();
$improj = new Improj(
    getenv('IMPROJ_TOKEN') ?: null,                    // improj_pat_... or improj_org_...
    httpClient: new Client(['timeout' => 30]),          // timeouts live on the HTTP client
    requestFactory: $factory,
    streamFactory: $factory,
);

$me = $improj->me->get();

foreach ($improj->projects->all() as $project) {
    echo $project->id, ' ', $project->name, PHP_EOL;
}

$task = $improj->tasks->create(42, ['title' => 'Prepare the kick-off agenda', 'due_date' => '2026-11-02']);
$improj->tasks->update(42, $task->id, ['status' => 'done']);
```

Without explicit factories the client uses `php-http/discovery` if it is installed (`composer require php-http/discovery`; allow its Composer plugin only if you want it to install implementations for you).

## Authentication

Create a token in IMPROJ under **Settings > Developers**. `improj_pat_...` acts as you; `improj_org_...` acts as the organisation owner or admin who created it, limited to that organisation's projects. Give each integration its own token with the fewest scopes, a project list, an expiry and an IP allow-list.

The SDK sends the token only as `Authorization: Bearer ...`, never in a URL and never with cookies, refuses plain `http` (except `localhost` for testing) and refuses redirects. `var_dump()`/`print_r()` show a masked token, and the client cannot be serialised. The token parameter is marked `#[\SensitiveParameter]`, so it is hidden from stack traces on PHP 8.2+.

| Constructor argument | Default | Meaning |
|---|---|---|
| `token` | `IMPROJ_TOKEN` env | The token |
| `baseUrl` | `https://improj.com/api/v1` | API base URL |
| `httpClient`, `requestFactory`, `streamFactory` | discovered | PSR-18 / PSR-17 implementations |
| `apiVersion` | `2026-10-24` | Sent as `IMPROJ-API-Version`; `null` sends none |
| `maxRetries` | `2` | Retries of safe requests |
| `maxRetryAfter` | `60` | A longer `Retry-After` (seconds) is not waited for |
| `autoIdempotencyKeys` | `true` | Random `Idempotency-Key` on every POST |
| `headers` | `[]` | Extra headers (`Authorization`, `Cookie` refused) |

Every method takes `?RequestOptions $options` last: `new RequestOptions(idempotencyKey: 'order-123', maxRetries: 0, headers: [...])`.

## Resources

| Property | Methods |
|---|---|
| `$improj->me` | `get()` |
| `$improj->projects` | `list()`, `all()`, `get($projectId)`, `workPackages($projectId)`, `activities($projectId, ['work_package_id' => ...])` |
| `$improj->tasks` | `list($projectId, ['status' => 'todo'])`, `all`, `get`, `create`, `update`, `delete` |
| `$improj->milestones` | `list`, `all`, `get`, `create`, `update`, `delete` |
| `$improj->members` | `list($projectId)`, `all` |
| `$improj->files` | `list($projectId, ['parent_id' => 'root'])`, `all`, `get`, `downloadLink`, `upload`, `uploadFile`, `createUpload`, `sendUpload` |
| `$improj->calendarEvents` | `list($projectId, ['from' => ..., 'to' => ...])`, `all`, `get`, `create`, `update`, `delete` |
| `$improj->calendar` | `list($from, $to)` (your own calendar) |
| `$improj->meetings` | `list`, `all`, `get` |
| `$improj->webhooks` | `list`, `all`, `create`, `get`, `update`, `delete`, `test`, `deliveries` |
| `$improj->agents` | `list()`, `items($agentId)`, `postNote($agentId, [...])` (Enterprise) |
| `$improj->openapi()`, `$improj->request($method, $path, $query, $body)` | the live OpenAPI document; any endpoint not wrapped yet |

Results are typed, read-only models (`Task`, `Project`, `ProjectFile`...) with camelCase properties; request arrays use the API's snake_case fields. `$model->toArray()` returns everything the API sent, including fields newer than this SDK.

## Pagination

```php
$page = $improj->tasks->list(42, ['status' => 'todo', 'limit' => 100]); // Page<Task>
foreach ($page as $task) { /* this page */ }
if ($page->hasNextPage()) {
    $next = $page->nextPage();
}

foreach ($improj->tasks->all(42) as $task) {
    // every task; pages are fetched lazily by a generator
}
```

Cursors are opaque and signed by the API. A repeated cursor stops the walk with `PAGINATION_LOOP` instead of looping forever.

## Errors

Every failure is an `ImprojException` with `getErrorCode()` (the API's stable code), `getStatus()`, `getRequestId()`, `getErrors()` (field problems of a 400) and `getRetryAfter()`. Subclasses: `BadRequestException` (400), `AuthenticationException` (401), `PermissionException` (403), `NotFoundException` (404), `ConflictException` (409), `UnprocessableException` (422), `RateLimitException` (429), `ServerException` (5xx), `ConnectionException` (no answer), `ConfigurationException`.

```php
use Kodvalley\Improj\Exception\ImprojException;
use Kodvalley\Improj\Exception\NotFoundException;

try {
    $task = $improj->tasks->get(42, 7);
} catch (NotFoundException) {
    // missing, or not visible to the token's owner (indistinguishable on purpose)
} catch (ImprojException $e) {
    error_log(json_encode($e->toArray())); // redacted; includes request_id for support
    throw $e;
}
```

Messages and details are redacted: a token or webhook secret never appears in them.

## Retries and idempotency

- Every POST carries a random `Idempotency-Key` (or yours), so a retried POST is never applied twice; the API replays the first answer for 24 hours.
- Retried automatically with exponential backoff and jitter, honouring `Retry-After`: `429` for any request; `503` and network errors for `GET`, `DELETE` and keyed `POST`.
- Never retried: `PATCH` after a `503` or a network error, any `500`, and the upload `PUT` after a network error (upload ids are single use).

## Uploads

```php
$file = $improj->files->uploadFile(42, '/path/to/report.pdf');                 // type via ext-fileinfo
$file = $improj->files->upload(42, 'notes.txt', $contents, 'text/plain', parentId: 1234);
```

The helper announces the file, then sends the bytes with an exact `Content-Length` to `<baseUrl>/uploads/<id>`. It builds that address itself and never sends the token to a URL taken from a response. Files up to 16 MB and your plan's per-file limit.

## Webhooks

```php
use Kodvalley\Improj\Webhook\SignatureVerificationException;
use Kodvalley\Improj\Webhook\WebhookVerifier;

$payload = file_get_contents('php://input');                    // the raw body, never json_decode first
$header = $_SERVER['HTTP_IMPROJ_SIGNATURE'] ?? null;

try {
    $event = (new WebhookVerifier((string) getenv('IMPROJ_WEBHOOK_SECRET')))->constructEvent($payload, $header);
} catch (SignatureVerificationException $e) {
    http_response_code(400);                                    // $e->getReason() says why
    exit;
}
// De-duplicate on $event->deliveryId, answer 204 fast, then fetch the object through the API.
```

The verifier computes `hash_hmac('sha256', "<t>." . $payload, $secret)`, compares every `v1` value with `hash_equals()` (constant time) and refuses timestamps more than 300 seconds away (`new WebhookVerifier($secret, tolerance: 600)` to change). Complete examples: [examples/webhook-plain.php](examples/webhook-plain.php) and the [Laravel middleware](examples/laravel/VerifyImprojWebhook.php).

## Security

- Keep tokens and webhook secrets in environment variables or a secret manager, never in source control.
- Never send an IMPROJ token to a browser; proxy through your backend.
- Configure timeouts on your HTTP client; with Symfony HttpClient also set `max_redirects: 0`.
- Verify every webhook over the raw body, de-duplicate deliveries, and answer quickly.
- Report vulnerabilities privately: see [SECURITY.md](SECURITY.md).

## Development

```sh
composer install
composer tools:install   # PHPStan, Psalm, PHP-CS-Fixer in tools/* with their own lockfiles
composer test
composer coverage        # needs pcov or Xdebug; fails below 90% of lines
composer analyse         # PHPStan level max, Psalm level 1 and taint analysis
composer cs              # PHP-CS-Fixer dry run
composer audit:all       # the library and every tool
composer check           # all of the above, as CI runs it
```

The library's `composer.lock` is resolved for PHP 8.1 (`config.platform.php`) so the same lock installs on 8.1 to 8.5. See [CONTRIBUTING.md](CONTRIBUTING.md) and [CHANGELOG.md](CHANGELOG.md). Licensed under the [MIT License](LICENSE).
