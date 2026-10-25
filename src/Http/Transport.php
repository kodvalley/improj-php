<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Http;

use JsonException;
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
use Kodvalley\Improj\Internal\Redactor;
use Kodvalley\Improj\RequestOptions;
use LogicException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Sends API requests: authentication, version pin, idempotency keys, retries
 * with backoff that honour Retry-After, and problem+json errors.
 *
 * Retry policy:
 * - 429: any request (the API refused it before doing anything);
 * - 503: GET, DELETE, POST with an Idempotency-Key, and upload PUTs;
 * - network errors: GET, DELETE and POST with an Idempotency-Key;
 * - never: PATCH after a 503 or a network error, any 500, an upload PUT after a network error.
 *
 * @internal Use Kodvalley\Improj\Improj.
 */
final class Transport
{
    public const SDK_VERSION = '0.1.0';
    public const API_VERSION = '2026-10-24';
    public const DEFAULT_BASE_URL = 'https://improj.com/api/v1';

    private const FORBIDDEN_HEADERS = ['authorization', 'cookie', 'host', 'content-length'];

    private readonly string $basePath;
    private readonly string $origin;

    /** @var callable(float): void */
    private $sleep;

    /** @var callable(): float */
    private $random;

    /**
     * @param array<string, string>     $defaultHeaders
     * @param (callable(float): void)|null $sleep  Seconds to wait (tests replace it).
     * @param (callable(): float)|null  $random Jitter source in [0, 1).
     */
    public function __construct(
        #[\SensitiveParameter]
        private readonly ?string $token,
        string $baseUrl,
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly ?string $apiVersion,
        private readonly int $maxRetries,
        private readonly int $maxRetryAfter,
        private readonly bool $autoIdempotencyKeys,
        private readonly array $defaultHeaders,
        ?callable $sleep = null,
        ?callable $random = null,
    ) {
        $parts = parse_url($baseUrl);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new ConfigurationException('baseUrl is not a valid absolute URL.', 'CONFIGURATION');
        }
        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'https' && $scheme !== 'http') {
            throw new ConfigurationException('baseUrl must use https.', 'CONFIGURATION');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new ConfigurationException('baseUrl must not contain credentials.', 'CONFIGURATION');
        }
        if (isset($parts['query']) || isset($parts['fragment'])) {
            throw new ConfigurationException('baseUrl must not contain a query or fragment.', 'CONFIGURATION');
        }
        $host = strtolower($parts['host']);
        if ($token !== null && $scheme === 'http' && !\in_array($host, ['localhost', '127.0.0.1', '[::1]'], true)) {
            throw new ConfigurationException('Refusing to send a token over plain http; use an https baseUrl.', 'CONFIGURATION');
        }
        if ($token !== null && preg_match('/^[\x21-\x7e]{8,512}$/', $token) !== 1) {
            throw new ConfigurationException('The token must be a single line of visible characters (improj_pat_..., improj_org_...).', 'CONFIGURATION');
        }
        if ($apiVersion !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $apiVersion) !== 1) {
            throw new ConfigurationException('apiVersion must look like 2026-10-24.', 'CONFIGURATION');
        }
        if ($maxRetries < 0 || $maxRetryAfter < 0) {
            throw new ConfigurationException('maxRetries and maxRetryAfter must not be negative.', 'CONFIGURATION');
        }
        self::checkHeaders($defaultHeaders);
        $this->origin = $scheme . '://' . $host . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $this->basePath = rtrim($parts['path'] ?? '', '/');
        $this->sleep = $sleep ?? static function (float $seconds): void {
            usleep((int) round($seconds * 1_000_000.0));
        };
        $this->random = $random ?? static fn(): float => (float) random_int(0, 1_000_000) / 1_000_000.0;
    }

    public function baseUrl(): string
    {
        return $this->origin . $this->basePath;
    }

    public function hasToken(): bool
    {
        return $this->token !== null;
    }

    /**
     * Sends a JSON request and returns the decoded body (null for 204).
     *
     * @param array<string, scalar|null> $query
     *
     * @return array<string, mixed>|list<mixed>|null
     */
    public function request(string $method, string $path, array $query = [], mixed $json = null, ?RequestOptions $options = null): ?array
    {
        return $this->send($method, $path, $query, $json === null ? null : ['json' => $json], $options, false);
    }

    /**
     * Sends raw bytes (the upload PUT). Retried on 429/503 only: upload ids are single use.
     *
     * @return array<string, mixed>|list<mixed>|null
     */
    public function upload(string $path, string $bytes, ?RequestOptions $options = null): ?array
    {
        return $this->send('PUT', $path, [], ['raw' => $bytes], $options, true);
    }

    /**
     * @param array<string, scalar|null>          $query
     * @param array{json?: mixed, raw?: string}|null $body
     *
     * @return array<string, mixed>|list<mixed>|null
     */
    private function send(string $method, string $path, array $query, ?array $body, ?RequestOptions $options, bool $statusOnly): ?array
    {
        if ($this->token === null && $this->origin === 'https://improj.com' && $path !== '/openapi.json') {
            throw new ConfigurationException('No token: pass one to new Improj($token) or set IMPROJ_TOKEN.', 'CONFIGURATION');
        }
        $url = $this->url($path, $query);
        $options ??= new RequestOptions();
        $maxRetries = $options->maxRetries ?? $this->maxRetries;
        if ($maxRetries < 0) {
            throw new ConfigurationException('maxRetries must not be negative.', 'CONFIGURATION');
        }
        self::checkHeaders($options->headers);

        $headers = ['Accept' => 'application/json', 'User-Agent' => 'improj-php/' . self::SDK_VERSION . ' php/' . \PHP_VERSION] + $this->defaultHeaders;
        foreach ($options->headers as $name => $value) {
            $headers[$name] = $value;
        }
        if ($this->token !== null) {
            $headers['Authorization'] = 'Bearer ' . $this->token;
        }
        if ($this->apiVersion !== null) {
            $headers['IMPROJ-API-Version'] = $this->apiVersion;
        }

        $idempotencyKey = null;
        if ($method === 'POST') {
            if ($options->idempotencyKey !== null) {
                if (preg_match('/^[\x21-\x7e]{1,255}$/', $options->idempotencyKey) !== 1) {
                    throw new ConfigurationException('idempotencyKey must be 1 to 255 visible ASCII characters.', 'CONFIGURATION');
                }
                $idempotencyKey = $options->idempotencyKey;
            } elseif ($this->autoIdempotencyKeys) {
                $idempotencyKey = self::uuid4();
            }
            if ($idempotencyKey !== null) {
                $headers['Idempotency-Key'] = $idempotencyKey;
            }
        } elseif ($options->idempotencyKey !== null) {
            throw new ConfigurationException('idempotencyKey applies to POST requests only.', 'CONFIGURATION');
        }

        $payload = null;
        if ($body !== null && \array_key_exists('json', $body)) {
            $headers['Content-Type'] = 'application/json';
            try {
                $payload = json_encode($body['json'], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION);
            } catch (JsonException $e) {
                throw new ConfigurationException('The request body cannot be encoded as JSON.', 'CONFIGURATION', previous: $e);
            }
        } elseif ($body !== null && isset($body['raw'])) {
            $headers['Content-Type'] = 'application/octet-stream';
            $payload = $body['raw'];
        }

        $replaySafe = $method === 'GET' || $method === 'DELETE' || ($method === 'POST' && $idempotencyKey !== null);
        $attempt = 0;
        $previousHadNoAnswer = false;
        while (true) {
            ++$attempt;
            $request = $this->requestFactory->createRequest($method, $url);
            foreach ($headers as $name => $value) {
                $request = $request->withHeader($name, $value);
            }
            if ($payload !== null) {
                // An exact Content-Length (the API requires it for uploads).
                $request = $request->withBody($this->streamFactory->createStream($payload))
                    ->withHeader('Content-Length', (string) \strlen($payload));
            }

            $response = $this->attempt($request, $replaySafe && !$statusOnly && $attempt <= $maxRetries, $method, $path);
            if ($response === null) {
                $previousHadNoAnswer = true;
                ($this->sleep)($this->backoff($attempt));
                continue;
            }

            $status = $response->getStatusCode();
            $requestId = $response->getHeaderLine('X-Request-Id');
            $requestId = $requestId !== '' ? $requestId : null;
            if ($status >= 200 && $status < 300) {
                return $this->decodeSuccess($response, $method, $path, $requestId);
            }
            if ($status >= 300 && $status < 400) {
                throw new ImprojException(sprintf('%s %s: unexpected redirect (%d); the API never redirects.', $method, $path, $status), 'UNEXPECTED_REDIRECT', $status, $requestId);
            }
            if ($method === 'DELETE' && $status === 404 && $previousHadNoAnswer) {
                return null; // an earlier attempt whose answer was lost already deleted it
            }

            $retryAfter = self::parseRetryAfter($response->getHeaderLine('Retry-After'));
            $error = self::buildError($response, $method, $path, $requestId, $retryAfter);
            $retryable = $status === 429 || ($status === 503 && ($replaySafe || $statusOnly));
            if ($retryable && $attempt <= $maxRetries) {
                $wait = $retryAfter ?? $this->backoff($attempt);
                if ($wait <= $this->maxRetryAfter) {
                    $previousHadNoAnswer = false;
                    ($this->sleep)((float) $wait);
                    continue;
                }
            }
            throw $error;
        }
    }

    /**
     * One attempt. Null means "no answer, and a retry is allowed".
     */
    private function attempt(RequestInterface $request, bool $mayRetry, string $method, string $path): ?ResponseInterface
    {
        try {
            return $this->client->sendRequest($request);
        } catch (NetworkExceptionInterface $e) {
            if ($mayRetry) {
                return null;
            }
            throw new ConnectionException(sprintf('%s %s failed before an answer arrived: %s', $method, $path, $e->getMessage()), 'CONNECTION_ERROR', previous: $e);
        } catch (ClientExceptionInterface $e) {
            throw new ConnectionException(sprintf('%s %s could not be sent: %s', $method, $path, $e->getMessage()), 'CONNECTION_ERROR', previous: $e);
        }
    }

    /**
     * @param array<string, scalar|null> $query
     */
    public function url(string $path, array $query = []): string
    {
        if ($path === '' || $path[0] !== '/' || str_contains($path, '..') || str_contains($path, '//')) {
            throw new ConfigurationException('Invalid API path.', 'CONFIGURATION');
        }
        $pairs = [];
        foreach ($query as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            $pairs[$k] = \is_bool($v) ? ($v ? 'true' : 'false') : (string) $v;
        }
        $qs = $pairs === [] ? '' : '?' . http_build_query($pairs, '', '&', \PHP_QUERY_RFC3986);

        return $this->origin . $this->basePath . $path . $qs;
    }

    /** Exponential backoff with jitter: 0.5 s, 1 s, 2 s... capped at 8 s, each scaled by 0.5 to 1. */
    public function backoff(int $attempt): float
    {
        $base = min(8.0, 0.5 * (float) (2 ** max(0, $attempt - 1)));

        return round($base * (0.5 + ($this->random)() / 2.0), 3);
    }

    /** Parses Retry-After (seconds or an HTTP date) into whole seconds. */
    public static function parseRetryAfter(string $value, ?int $now = null): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (ctype_digit($value)) {
            return (int) $value;
        }
        $at = strtotime($value);
        if ($at === false) {
            return null;
        }

        return max(0, $at - ($now ?? time()));
    }

    /** A random RFC 4122 version 4 UUID. */
    public static function uuid4(): string
    {
        $b = random_bytes(16);
        $b[6] = \chr((\ord($b[6]) & 0x0F) | 0x40);
        $b[8] = \chr((\ord($b[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    /**
     * @param array<array-key, mixed> $headers
     */
    private static function checkHeaders(array $headers): void
    {
        foreach ($headers as $name => $value) {
            if (!\is_string($name) || preg_match('/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/', $name) !== 1) {
                throw new ConfigurationException('Invalid header name.', 'CONFIGURATION');
            }
            if (\in_array(strtolower($name), self::FORBIDDEN_HEADERS, true)) {
                throw new ConfigurationException(sprintf('The %s header cannot be set here.', $name), 'CONFIGURATION');
            }
            if (!\is_string($value) || preg_match('/[\x00-\x08\x0A-\x1F\x7F]/', $value) === 1) {
                throw new ConfigurationException(sprintf('Header %s must be a string without control characters.', $name), 'CONFIGURATION');
            }
        }
    }

    /**
     * @return array<string, mixed>|list<mixed>|null
     */
    private function decodeSuccess(ResponseInterface $response, string $method, string $path, ?string $requestId): ?array
    {
        $text = (string) $response->getBody();
        if ($response->getStatusCode() === 204 || $text === '') {
            return null;
        }
        if (preg_match('/\bjson\b/i', $response->getHeaderLine('Content-Type')) !== 1) {
            $type = $response->getHeaderLine('Content-Type');
            throw new ImprojException(sprintf('%s %s: expected JSON, got %s.', $method, $path, $type !== '' ? $type : 'no content type'), 'INVALID_RESPONSE', $response->getStatusCode(), $requestId);
        }
        try {
            /** @var mixed $decoded */
            $decoded = json_decode($text, true, 512, \JSON_THROW_ON_ERROR | \JSON_BIGINT_AS_STRING);
        } catch (JsonException $e) {
            throw new ImprojException(sprintf('%s %s: the answer is not valid JSON.', $method, $path), 'INVALID_RESPONSE', $response->getStatusCode(), $requestId, previous: $e);
        }
        if (!\is_array($decoded)) {
            throw new ImprojException(sprintf('%s %s: the answer is not a JSON object.', $method, $path), 'INVALID_RESPONSE', $response->getStatusCode(), $requestId);
        }

        /** @var array<string, mixed>|list<mixed> $decoded */
        return $decoded;
    }

    private static function buildError(ResponseInterface $response, string $method, string $path, ?string $requestId, ?int $retryAfter): ImprojException
    {
        $status = $response->getStatusCode();
        $problem = [];
        if (preg_match('/\bjson\b/i', $response->getHeaderLine('Content-Type')) === 1) {
            try {
                /** @var mixed $decoded */
                $decoded = json_decode((string) $response->getBody(), true, 64, \JSON_THROW_ON_ERROR);
                if (\is_array($decoded) && !array_is_list($decoded)) {
                    $problem = $decoded;
                }
            } catch (JsonException) {
                $problem = [];
            }
        }
        $code = isset($problem['code']) && \is_string($problem['code']) && preg_match('/^[A-Z0-9_]{1,64}$/', $problem['code']) === 1 ? $problem['code'] : 'HTTP_' . $status;
        $detail = isset($problem['detail']) && \is_string($problem['detail']) ? $problem['detail'] : 'HTTP ' . $status;
        if ($requestId === null && isset($problem['request_id']) && \is_string($problem['request_id']) && $problem['request_id'] !== '') {
            $requestId = $problem['request_id'];
        }
        $errors = [];
        if (isset($problem['errors']) && \is_array($problem['errors'])) {
            /** @var mixed $e */
            foreach (\array_slice($problem['errors'], 0, 50) as $e) {
                if (\is_array($e)) {
                    $errors[] = [
                        'path' => isset($e['path']) && \is_scalar($e['path']) ? (string) $e['path'] : '',
                        'message' => Redactor::redact(isset($e['message']) && \is_scalar($e['message']) ? (string) $e['message'] : ''),
                    ];
                }
            }
        }
        $type = isset($problem['type']) && \is_string($problem['type']) ? $problem['type'] : null;
        $message = sprintf('%s: %s%s', $code, $detail, $requestId !== null ? " (request {$requestId})" : '');
        $class = match (true) {
            $status === 400 => BadRequestException::class,
            $status === 401 => AuthenticationException::class,
            $status === 403 => PermissionException::class,
            $status === 404 => NotFoundException::class,
            $status === 409 => ConflictException::class,
            $status === 422 => UnprocessableException::class,
            $status === 429 => RateLimitException::class,
            $status >= 500 => ServerException::class,
            default => ImprojException::class,
        };

        return new $class($message, $code, $status, $requestId, $errors, $retryAfter, $type, $detail);
    }

    /** The token is never shown. */
    public function __debugInfo(): array
    {
        return ['baseUrl' => $this->baseUrl(), 'token' => Redactor::maskToken($this->token), 'apiVersion' => $this->apiVersion];
    }

    /** Clients are not serialisable, so a token cannot end up in a cache or session. */
    public function __serialize(): array
    {
        throw new LogicException('The IMPROJ client cannot be serialised.');
    }
}
