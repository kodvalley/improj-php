<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Tests\Support;

use Closure;
use Kodvalley\Improj\Improj;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * A PSR-18 client that answers with queued handlers (the last one repeats)
 * and records every request.
 */
final class MockClient implements ClientInterface
{
    /** Obviously fake: never matches a real token pattern. */
    public const TOKEN = 'improj_pat_TEST_ONLY_not_a_real_token';

    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @var list<Closure(RequestInterface): ResponseInterface> */
    private array $handlers;

    private int $i = 0;

    /** @var list<float> */
    public array $sleeps = [];

    /**
     * @param Closure(RequestInterface): ResponseInterface ...$handlers
     */
    public function __construct(Closure ...$handlers)
    {
        $this->handlers = array_values($handlers);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        $handler = $this->handlers[min($this->i, \count($this->handlers) - 1)] ?? throw new RuntimeException('No handler');
        ++$this->i;

        return $handler($request);
    }

    /**
     * A client wired to this mock; named arguments override the defaults.
     *
     * @param array<string, string> $headers
     */
    public function client(
        ?string $token = self::TOKEN,
        string $baseUrl = Improj::DEFAULT_BASE_URL,
        ?string $apiVersion = Improj::API_VERSION,
        int $maxRetries = 2,
        int $maxRetryAfter = 60,
        bool $autoIdempotencyKeys = true,
        array $headers = [],
    ): Improj {
        $factory = new Psr17Factory();

        return new Improj(
            $token,
            $baseUrl,
            $this,
            $factory,
            $factory,
            $apiVersion,
            $maxRetries,
            $maxRetryAfter,
            $autoIdempotencyKeys,
            $headers,
            function (float $s): void {
                $this->sleeps[] = $s;
            },
        );
    }

    public function last(): RequestInterface
    {
        return $this->requests[\count($this->requests) - 1] ?? throw new RuntimeException('No request');
    }

    /**
     * @param array<string, string> $headers
     */
    public static function json(int $status, mixed $body, array $headers = []): Closure
    {
        return static fn(): ResponseInterface => new Response(
            $status,
            $headers + ['Content-Type' => 'application/json', 'X-Request-Id' => 'req_php_1'],
            $status === 204 ? null : json_encode($body, \JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>  $extra
     */
    public static function problem(int $status, string $code, array $headers = [], array $extra = []): Closure
    {
        return static fn(): ResponseInterface => new Response(
            $status,
            $headers + ['Content-Type' => 'application/problem+json', 'X-Request-Id' => 'req_problem_1'],
            json_encode($extra + [
                'type' => 'https://improj.com/developers#error-' . $code,
                'title' => 'Error',
                'status' => $status,
                'detail' => "Detail for {$code}.",
                'instance' => '/api/v1/test',
                'code' => $code,
                'request_id' => 'req_problem_1',
            ], \JSON_THROW_ON_ERROR),
        );
    }

    public static function networkFailure(): Closure
    {
        return static fn(RequestInterface $r): ResponseInterface => throw new NetworkFailure($r);
    }
}
