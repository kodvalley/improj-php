<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Tests;

use Kodvalley\Improj\Exception\AuthenticationException;
use Kodvalley\Improj\Exception\BadRequestException;
use Kodvalley\Improj\Exception\ConfigurationException;
use Kodvalley\Improj\Exception\ConflictException;
use Kodvalley\Improj\Exception\ConnectionException;
use Kodvalley\Improj\Exception\ImprojException;
use Kodvalley\Improj\Exception\NotFoundException;
use Kodvalley\Improj\Exception\PermissionException;
use Kodvalley\Improj\Exception\RateLimitException;
use Kodvalley\Improj\Exception\ServerException;
use Kodvalley\Improj\Exception\UnprocessableException;
use Kodvalley\Improj\Http\Transport;
use Kodvalley\Improj\Improj;
use Kodvalley\Improj\RequestOptions;
use Kodvalley\Improj\Tests\Support\BrokenRequest;
use Kodvalley\Improj\Tests\Support\MockClient;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class TransportTest extends TestCase
{
    public function testSendsTokenVersionAcceptAndUserAgent(): void
    {
        $m = new MockClient(MockClient::json(200, ['data' => ['id' => 1, 'name' => 'A', 'email' => null, 'avatar_url' => null, 'time_zone' => null, 'token' => ['id' => 2, 'kind' => 'pat', 'scopes' => ['user:read'], 'organization_id' => null, 'expires_at' => null]]]));
        $me = $m->client()->me->get();
        self::assertSame(1, $me->id);
        self::assertSame(['user:read'], $me->token->scopes);
        $r = $m->last();
        self::assertSame('GET', $r->getMethod());
        self::assertSame('https://improj.com/api/v1/me', (string) $r->getUri());
        self::assertSame('Bearer ' . MockClient::TOKEN, $r->getHeaderLine('Authorization'));
        self::assertSame('2026-10-24', $r->getHeaderLine('IMPROJ-API-Version'));
        self::assertSame('application/json', $r->getHeaderLine('Accept'));
        self::assertMatchesRegularExpression('#^improj-php/\d+\.\d+\.\d+ php/#', $r->getHeaderLine('User-Agent'));
        self::assertFalse($r->hasHeader('Cookie'));
    }

    public function testApiVersionCanBeLeftOut(): void
    {
        $m = new MockClient(MockClient::json(200, ['openapi' => '3.1.0']));
        $m->client(apiVersion: null)->openapi();
        self::assertFalse($m->last()->hasHeader('IMPROJ-API-Version'));
    }

    public function testEncodesQueryAndDropsEmptyValues(): void
    {
        $m = new MockClient(MockClient::json(200, ['data' => [], 'next_cursor' => null]));
        $m->client()->request('get', '/x', ['a' => 'b c&d', 'n' => 5, 't' => true, 'f' => false, 'z' => null, 'e' => '']);
        self::assertSame('a=b%20c%26d&n=5&t=true&f=false', $m->last()->getUri()->getQuery());
    }

    public function testRefusesUnsafePaths(): void
    {
        $c = (new MockClient(MockClient::json(200, [])))->client();
        foreach (['x', '/a/../b', '//evil.example', ''] as $path) {
            try {
                $c->request('GET', $path);
                self::fail("accepted {$path}");
            } catch (ConfigurationException $e) {
                self::assertSame('CONFIGURATION', $e->getErrorCode());
            }
        }
    }

    public function testSendsJsonAndParsesTheAnswer(): void
    {
        $m = new MockClient(MockClient::json(201, ['data' => ['ok' => true]]));
        $out = $m->client()->request('POST', '/x', [], ['title' => 'Ä/é', 'n' => 1.0]);
        self::assertSame(['data' => ['ok' => true]], $out);
        self::assertSame('application/json', $m->last()->getHeaderLine('Content-Type'));
        self::assertSame('{"title":"Ä/é","n":1.0}', (string) $m->last()->getBody());
        self::assertSame((string) \strlen('{"title":"Ä/é","n":1.0}'), $m->last()->getHeaderLine('Content-Length'));
    }

    public function testRefusesUnencodableBodies(): void
    {
        $this->expectException(ConfigurationException::class);
        (new MockClient(MockClient::json(200, [])))->client()->request('POST', '/x', [], ['bad' => "\xB1\x31"]);
    }

    public function testNoContentAndEmptyBodies(): void
    {
        $m = new MockClient(MockClient::json(204, null), static fn() => new Response(200, ['Content-Type' => 'application/json'], ''));
        self::assertNull($m->client()->request('DELETE', '/x'));
        self::assertNull($m->client()->request('GET', '/x'));
    }

    public function testRefusesNonJsonAndBrokenJson(): void
    {
        foreach ([
            static fn() => new Response(200, ['Content-Type' => 'text/html'], '<html>'),
            static fn() => new Response(200, ['Content-Type' => 'application/json'], '{oops'),
            static fn() => new Response(200, ['Content-Type' => 'application/json'], '"text"'),
        ] as $handler) {
            try {
                (new MockClient($handler))->client()->request('GET', '/x');
                self::fail('accepted');
            } catch (ImprojException $e) {
                self::assertSame('INVALID_RESPONSE', $e->getErrorCode());
            }
        }
    }

    public function testRefusesRedirects(): void
    {
        $m = new MockClient(static fn() => new Response(302, ['Location' => 'https://evil.example/']));
        try {
            $m->client()->request('GET', '/x');
            self::fail('followed');
        } catch (ImprojException $e) {
            self::assertSame('UNEXPECTED_REDIRECT', $e->getErrorCode());
            self::assertSame(302, $e->getStatus());
        }
    }

    /**
     * @return iterable<string, array{int, string, class-string<ImprojException>}>
     */
    public static function statuses(): iterable
    {
        yield '400' => [400, 'INVALID_INPUT', BadRequestException::class];
        yield '401' => [401, 'API_TOKEN_INVALID', AuthenticationException::class];
        yield '403' => [403, 'API_SCOPE_MISSING', PermissionException::class];
        yield '404' => [404, 'NOT_FOUND', NotFoundException::class];
        yield '409' => [409, 'IDEMPOTENCY_KEY_IN_USE', ConflictException::class];
        yield '422' => [422, 'IDEMPOTENCY_KEY_REUSED', UnprocessableException::class];
        yield '429' => [429, 'RATE_LIMITED', RateLimitException::class];
        yield '500' => [500, 'SOMETHING_WENT_WRONG', ServerException::class];
        yield '413' => [413, 'API_PAYLOAD_TOO_LARGE', ImprojException::class];
    }

    /**
     * @param class-string<ImprojException> $class
     */
    #[DataProvider('statuses')]
    public function testMapsProblemsToExceptions(int $status, string $code, string $class): void
    {
        $m = new MockClient(MockClient::problem($status, $code, ['Retry-After' => '120']));
        try {
            $m->client(maxRetries: 0)->request('PATCH', '/x', [], ['a' => 1]);
            self::fail('no exception');
        } catch (ImprojException $e) {
            self::assertInstanceOf($class, $e);
            self::assertSame($status, $e->getStatus());
            self::assertSame($status, $e->getCode());
            self::assertSame($code, $e->getErrorCode());
            self::assertSame('req_problem_1', $e->getRequestId());
            self::assertSame("{$code}: Detail for {$code}. (request req_problem_1)", $e->getMessage());
            self::assertSame(120, $e->getRetryAfter());
            self::assertSame('https://improj.com/developers#error-' . $code, $e->getType());
        }
    }

    public function testKeepsFieldErrorsAndRedactsSecrets(): void
    {
        $leaked = 'improj_pat_' . str_repeat('Ab3', 16) . 'Z';
        $m = new MockClient(MockClient::problem(400, 'INVALID_INPUT', [], [
            'detail' => "bad {$leaked} whsec_abcdefabcdef",
            'errors' => [['path' => 'due_date', 'message' => 'Use YYYY-MM-DD'], ['path' => 'x', 'message' => "has {$leaked}"], 'junk'],
        ]));
        try {
            $m->client()->request('GET', '/x');
            self::fail('no exception');
        } catch (BadRequestException $e) {
            self::assertSame([['path' => 'due_date', 'message' => 'Use YYYY-MM-DD'], ['path' => 'x', 'message' => 'has improj_pat_***']], $e->getErrors());
            self::assertStringNotContainsString($leaked, $e->getMessage() . (string) $e->getDetail());
            self::assertStringContainsString('whsec_***', (string) $e->getDetail());
            self::assertSame('INVALID_INPUT', $e->toArray()['error_code']);
        }
    }

    public function testErrorsWithoutAProblemBody(): void
    {
        $m = new MockClient(static fn() => new Response(502, ['Content-Type' => 'text/plain'], 'Bad gateway'), static fn() => new Response(400, ['Content-Type' => 'application/json'], '[1]'), static fn() => new Response(400, ['Content-Type' => 'application/json'], '{bad'), MockClient::json(400, ['code' => 'lower <x>', 'request_id' => 'req_body']));
        $c = $m->client();
        foreach (['HTTP_502', 'HTTP_400', 'HTTP_400', 'HTTP_400'] as $i => $expected) {
            try {
                $c->request('GET', '/x');
                self::fail('no exception');
            } catch (ImprojException $e) {
                self::assertSame($expected, $e->getErrorCode());
                if ($i === 3) {
                    self::assertSame('req_php_1', $e->getRequestId());
                }
            }
        }
    }

    public function testRequestIdFromTheBodyWhenTheHeaderIsMissing(): void
    {
        $m = new MockClient(static fn() => new Response(404, ['Content-Type' => 'application/problem+json'], '{"code":"NOT_FOUND","request_id":"req_from_body"}'));
        try {
            $m->client()->request('GET', '/x');
        } catch (NotFoundException $e) {
            self::assertSame('req_from_body', $e->getRequestId());
        }
    }

    public function testIdempotencyKeys(): void
    {
        $m = new MockClient(MockClient::json(201, []));
        $c = $m->client();
        $c->request('POST', '/x', [], []);
        $c->request('POST', '/x', [], []);
        $a = $m->requests[0]->getHeaderLine('Idempotency-Key');
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $a);
        self::assertNotSame($a, $m->requests[1]->getHeaderLine('Idempotency-Key'));

        $c->request('POST', '/x', [], [], new RequestOptions(idempotencyKey: 'order-123'));
        self::assertSame('order-123', $m->last()->getHeaderLine('Idempotency-Key'));

        $m2 = new MockClient(MockClient::json(201, []));
        $m2->client(autoIdempotencyKeys: false)->request('POST', '/x', [], []);
        self::assertFalse($m2->last()->hasHeader('Idempotency-Key'));
    }

    public function testIdempotencyKeyValidation(): void
    {
        $c = (new MockClient(MockClient::json(200, [])))->client();
        foreach ([['POST', 'has space'], ['POST', str_repeat('x', 256)], ['PATCH', 'k']] as [$method, $key]) {
            try {
                $c->request($method, '/x', [], [], new RequestOptions(idempotencyKey: $key));
                self::fail('accepted');
            } catch (ConfigurationException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testRetriesAPostWithTheSameKey(): void
    {
        $m = new MockClient(MockClient::problem(503, 'DB_UNAVAILABLE', ['Retry-After' => '0']), MockClient::json(201, [], ['Idempotent-Replayed' => 'true']));
        $m->client()->request('POST', '/x', [], [], new RequestOptions(idempotencyKey: 'k1'));
        self::assertCount(2, $m->requests);
        self::assertSame(['k1', 'k1'], array_map(static fn(RequestInterface $r): string => $r->getHeaderLine('Idempotency-Key'), $m->requests));
        self::assertSame([0.0], $m->sleeps);
    }

    public function testRetryPolicy(): void
    {
        // 503 on GET: retried, honouring Retry-After.
        $m = new MockClient(MockClient::problem(503, 'SERVER_BUSY', ['Retry-After' => '2']), MockClient::json(200, ['ok' => 1]));
        self::assertSame(['ok' => 1], $m->client()->request('GET', '/x'));
        self::assertSame([2.0], $m->sleeps);

        // 429 on PATCH: retried (refused before anything ran).
        $m = new MockClient(MockClient::problem(429, 'RATE_LIMITED', ['Retry-After' => '0']), MockClient::json(200, []));
        $m->client()->request('PATCH', '/x', [], []);
        self::assertCount(2, $m->requests);

        // 503 on PATCH: not retried.
        $m = new MockClient(MockClient::problem(503, 'DB_UNAVAILABLE'));
        $this->assertThrowsClass(ServerException::class, static fn() => $m->client()->request('PATCH', '/x', [], []));
        self::assertCount(1, $m->requests);

        // 503 on POST without a key: not retried.
        $m = new MockClient(MockClient::problem(503, 'DB_UNAVAILABLE'));
        $this->assertThrowsClass(ServerException::class, static fn() => $m->client(autoIdempotencyKeys: false)->request('POST', '/x', [], []));
        self::assertCount(1, $m->requests);

        // 500: never retried.
        $m = new MockClient(MockClient::problem(500, 'SOMETHING_WENT_WRONG'));
        $this->assertThrowsClass(ServerException::class, static fn() => $m->client()->request('GET', '/x'));
        self::assertCount(1, $m->requests);

        // Gives up after maxRetries.
        $m = new MockClient(MockClient::problem(429, 'RATE_LIMITED', ['Retry-After' => '1']));
        $this->assertThrowsClass(RateLimitException::class, static fn() => $m->client(maxRetries: 2)->request('GET', '/x'));
        self::assertCount(3, $m->requests);

        // Per-request maxRetries.
        $m = new MockClient(MockClient::problem(429, 'RATE_LIMITED', ['Retry-After' => '1']));
        $this->assertThrowsClass(RateLimitException::class, static fn() => $m->client()->request('GET', '/x', [], null, new RequestOptions(maxRetries: 0)));
        self::assertCount(1, $m->requests);

        // A Retry-After beyond maxRetryAfter is not waited for.
        $m = new MockClient(MockClient::problem(429, 'RATE_LIMITED', ['Retry-After' => '3600']));
        $this->assertThrowsClass(RateLimitException::class, static fn() => $m->client()->request('GET', '/x'));
        self::assertCount(1, $m->requests);
        self::assertSame([], $m->sleeps);

        // Backoff with jitter when there is no Retry-After.
        $m = new MockClient(MockClient::problem(503, 'SERVER_BUSY'), MockClient::json(200, []));
        $m->client()->request('GET', '/x');
        self::assertCount(1, $m->sleeps);
        self::assertGreaterThanOrEqual(0.25, $m->sleeps[0]);
        self::assertLessThanOrEqual(0.5, $m->sleeps[0]);
    }

    public function testNetworkErrors(): void
    {
        // GET: retried.
        $m = new MockClient(MockClient::networkFailure(), MockClient::json(200, ['ok' => true]));
        self::assertSame(['ok' => true], $m->client()->request('GET', '/x'));

        // PATCH: not retried.
        $m = new MockClient(MockClient::networkFailure(), MockClient::json(200, []));
        try {
            $m->client()->request('PATCH', '/x', [], []);
            self::fail('no exception');
        } catch (ConnectionException $e) {
            self::assertSame('CONNECTION_ERROR', $e->getErrorCode());
            self::assertSame(0, $e->getStatus());
        }
        self::assertCount(1, $m->requests);

        // A request the client cannot send is not retried either.
        $m = new MockClient(static fn(RequestInterface $r) => throw new BrokenRequest($r));
        $this->assertThrowsClass(ConnectionException::class, static fn() => $m->client()->request('GET', '/x'));
        self::assertCount(1, $m->requests);
    }

    public function testDeleteThat404sAfterALostAnswerIsDone(): void
    {
        $m = new MockClient(MockClient::networkFailure(), MockClient::problem(404, 'NOT_FOUND'));
        self::assertNull($m->client()->request('DELETE', '/x'));
        $m = new MockClient(MockClient::problem(404, 'NOT_FOUND'));
        $this->assertThrowsClass(NotFoundException::class, static fn() => $m->client()->request('DELETE', '/x'));
    }

    /**
     * @return iterable<string, array{\Closure(MockClient): Improj, string}>
     */
    public static function badOptions(): iterable
    {
        yield 'ftp' => [static fn(MockClient $m) => $m->client(baseUrl: 'ftp://improj.com/api/v1'), 'https'];
        yield 'relative' => [static fn(MockClient $m) => $m->client(baseUrl: '/api'), 'absolute'];
        yield 'credentials' => [static fn(MockClient $m) => $m->client(baseUrl: 'https://u:p@improj.com/api/v1'), 'credentials'];
        yield 'query' => [static fn(MockClient $m) => $m->client(baseUrl: 'https://improj.com/api/v1?x=1'), 'query'];
        yield 'plain http' => [static fn(MockClient $m) => $m->client(baseUrl: 'http://api.example.com/v1'), 'plain http'];
        yield 'token whitespace' => [static fn(MockClient $m) => $m->client(token: "improj_pat_a b\r\nX: 1"), 'single line'];
        yield 'api version' => [static fn(MockClient $m) => $m->client(apiVersion: 'latest'), 'apiVersion'];
        yield 'retries' => [static fn(MockClient $m) => $m->client(maxRetries: -1), 'negative'];
        yield 'authorization header' => [static fn(MockClient $m) => $m->client(headers: ['Authorization' => 'Bearer x']), 'Authorization'];
        yield 'cookie header' => [static fn(MockClient $m) => $m->client(headers: ['Cookie' => 'a=1']), 'Cookie'];
        yield 'header name' => [static fn(MockClient $m) => $m->client(headers: ['Bad Header' => 'x']), 'Invalid header name'];
        yield 'header value' => [static fn(MockClient $m) => $m->client(headers: ['X-Ok' => "a\nb"]), 'control characters'];
    }

    /**
     * @param \Closure(MockClient): Improj $make
     */
    #[DataProvider('badOptions')]
    public function testValidatesOptions(\Closure $make, string $message): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage($message);
        $make(new MockClient(MockClient::json(200, [])));
    }

    public function testLoopbackHttpIsAllowedForLocalTesting(): void
    {
        $m = new MockClient(MockClient::json(200, ['openapi' => '3.1.0']));
        $c = $m->client(baseUrl: 'http://127.0.0.1:4010/api/v1/');
        self::assertSame('http://127.0.0.1:4010/api/v1', $c->baseUrl());
        $c->openapi();
        self::assertSame('http://127.0.0.1:4010/api/v1/openapi.json', (string) $m->last()->getUri());
    }

    public function testPerRequestHeadersAreValidated(): void
    {
        $m = new MockClient(MockClient::json(200, []));
        $c = $m->client(headers: ['X-App' => 'sync']);
        $c->request('GET', '/x', [], null, new RequestOptions(headers: ['X-Trace' => 't1']));
        self::assertSame('sync', $m->last()->getHeaderLine('X-App'));
        self::assertSame('t1', $m->last()->getHeaderLine('X-Trace'));
        $this->assertThrowsClass(ConfigurationException::class, static fn() => $c->request('GET', '/x', [], null, new RequestOptions(headers: ['authorization' => 'x'])));
        $this->assertThrowsClass(ConfigurationException::class, static fn() => $c->request('GET', '/x', [], null, new RequestOptions(maxRetries: -2)));
    }

    public function testNeedsATokenForTheOfficialApi(): void
    {
        $previous = getenv('IMPROJ_TOKEN');
        putenv('IMPROJ_TOKEN');
        try {
            $f = new Psr17Factory();
            $m = new MockClient(MockClient::json(200, ['openapi' => '3.1.0']));
            $c = new Improj(null, httpClient: $m, requestFactory: $f, streamFactory: $f);
            self::assertSame(['openapi' => '3.1.0'], $c->openapi());
            self::assertFalse($m->last()->hasHeader('Authorization'));
            $this->assertThrowsClass(ConfigurationException::class, static fn() => $c->me->get());
        } finally {
            putenv($previous === false ? 'IMPROJ_TOKEN' : "IMPROJ_TOKEN={$previous}");
        }
    }

    public function testReadsTheTokenFromTheEnvironment(): void
    {
        putenv('IMPROJ_TOKEN=' . MockClient::TOKEN);
        try {
            $f = new Psr17Factory();
            $m = new MockClient(MockClient::json(200, ['openapi' => '3.1.0']));
            (new Improj(httpClient: $m, requestFactory: $f, streamFactory: $f))->openapi();
            self::assertSame('Bearer ' . MockClient::TOKEN, $m->last()->getHeaderLine('Authorization'));
        } finally {
            putenv('IMPROJ_TOKEN');
        }
    }

    public function testDiscoveryFailureExplainsWhatToDo(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('PSR-18');
        new Improj(MockClient::TOKEN);
    }

    public function testTheTokenNeverShowsAndTheClientCannotBeSerialised(): void
    {
        $c = (new MockClient(MockClient::json(200, [])))->client();
        $dump = print_r($c, true) . var_export($c->__debugInfo(), true);
        self::assertStringNotContainsString(MockClient::TOKEN, $dump);
        self::assertStringContainsString('improj_pat_***', $dump);
        $this->expectException(LogicException::class);
        serialize($c);
    }

    public function testHelpers(): void
    {
        self::assertNull(Transport::parseRetryAfter(''));
        self::assertSame(12, Transport::parseRetryAfter('12'));
        self::assertNull(Transport::parseRetryAfter('soon'));
        $now = (int) strtotime('2026-10-24 10:00:00 UTC');
        self::assertSame(30, Transport::parseRetryAfter('Sat, 24 Oct 2026 10:00:30 GMT', $now));
        self::assertSame(0, Transport::parseRetryAfter('Sat, 24 Oct 2026 09:00:00 GMT', $now));
        self::assertSame(36, \strlen(Transport::uuid4()));

        $f = new Psr17Factory();
        $t = new Transport(MockClient::TOKEN, 'https://improj.com/api/v1', new MockClient(), $f, $f, null, 2, 60, true, [], null, static fn(): float => 1.0);
        self::assertSame(1.0, $t->backoff(2));
        self::assertSame(8.0, $t->backoff(10));
        self::assertTrue($t->hasToken());
        $t2 = new Transport(MockClient::TOKEN, 'https://improj.com/api/v1', new MockClient(), $f, $f, null, 2, 60, true, []);
        self::assertGreaterThanOrEqual(0.25, $t2->backoff(1));
    }

    /**
     * @param class-string<\Throwable> $class
     */
    private function assertThrowsClass(string $class, callable $fn): void
    {
        try {
            $fn();
            self::fail("expected {$class}");
        } catch (\Throwable $e) {
            self::assertInstanceOf($class, $e);
        }
    }
}
