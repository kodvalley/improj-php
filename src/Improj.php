<?php

declare(strict_types=1);

namespace Kodvalley\Improj;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Kodvalley\Improj\Exception\ConfigurationException;
use Kodvalley\Improj\Http\Transport;
use Kodvalley\Improj\Resource\AgentsResource;
use Kodvalley\Improj\Resource\CalendarEventsResource;
use Kodvalley\Improj\Resource\CalendarResource;
use Kodvalley\Improj\Resource\FilesResource;
use Kodvalley\Improj\Resource\MeetingsResource;
use Kodvalley\Improj\Resource\MembersResource;
use Kodvalley\Improj\Resource\MeResource;
use Kodvalley\Improj\Resource\MilestonesResource;
use Kodvalley\Improj\Resource\ProjectsResource;
use Kodvalley\Improj\Resource\TasksResource;
use Kodvalley\Improj\Resource\WebhooksResource;
use LogicException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * The IMPROJ API client.
 *
 *     $improj = new Improj(getenv('IMPROJ_TOKEN') ?: null, httpClient: new GuzzleHttp\Client(['timeout' => 30]));
 *     foreach ($improj->tasks->all(42, ['status' => 'todo']) as $task) {
 *         echo $task->title, PHP_EOL;
 *     }
 *
 * Any PSR-18 client works (Guzzle, Symfony HttpClient...). Without one, the
 * client is found with php-http/discovery when that package is installed.
 * Configure timeouts on your HTTP client: PSR-18 has no per-request timeout.
 */
final class Improj
{
    public const SDK_VERSION = Transport::SDK_VERSION;
    public const API_VERSION = Transport::API_VERSION;
    public const DEFAULT_BASE_URL = Transport::DEFAULT_BASE_URL;

    public readonly MeResource $me;
    public readonly ProjectsResource $projects;
    public readonly TasksResource $tasks;
    public readonly MilestonesResource $milestones;
    public readonly MembersResource $members;
    public readonly FilesResource $files;
    public readonly CalendarEventsResource $calendarEvents;
    public readonly CalendarResource $calendar;
    public readonly MeetingsResource $meetings;
    public readonly WebhooksResource $webhooks;
    public readonly AgentsResource $agents;

    private readonly Transport $transport;

    /**
     * @param string|null                  $token              improj_pat_... or improj_org_...; null reads IMPROJ_TOKEN
     * @param string                       $baseUrl            default https://improj.com/api/v1
     * @param string|null                  $apiVersion         sent as IMPROJ-API-Version; null sends none
     * @param int                          $maxRetries         retries of safe requests on 429, 503 and network errors
     * @param int                          $maxRetryAfter      a longer Retry-After (seconds) is not waited for
     * @param bool                         $autoIdempotencyKeys a random Idempotency-Key on every POST
     * @param array<string, string>        $headers            extra headers on every request
     * @param (callable(float): void)|null $sleep              how to wait between retries (tests)
     */
    public function __construct(
        #[\SensitiveParameter]
        ?string $token = null,
        string $baseUrl = Transport::DEFAULT_BASE_URL,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?string $apiVersion = Transport::API_VERSION,
        int $maxRetries = 2,
        int $maxRetryAfter = 60,
        bool $autoIdempotencyKeys = true,
        array $headers = [],
        ?callable $sleep = null,
    ) {
        if ($token === null) {
            $env = getenv('IMPROJ_TOKEN');
            $token = \is_string($env) && trim($env) !== '' ? trim($env) : null;
        }
        $this->transport = new Transport(
            $token,
            $baseUrl,
            $httpClient ?? self::discover('Psr18ClientDiscovery', static fn(): ClientInterface => Psr18ClientDiscovery::find()),
            $requestFactory ?? self::discover('Psr17FactoryDiscovery', static fn(): RequestFactoryInterface => Psr17FactoryDiscovery::findRequestFactory()),
            $streamFactory ?? self::discover('Psr17FactoryDiscovery', static fn(): StreamFactoryInterface => Psr17FactoryDiscovery::findStreamFactory()),
            $apiVersion,
            $maxRetries,
            $maxRetryAfter,
            $autoIdempotencyKeys,
            $headers,
            $sleep,
        );
        $this->me = new MeResource($this->transport);
        $this->projects = new ProjectsResource($this->transport);
        $this->tasks = new TasksResource($this->transport);
        $this->milestones = new MilestonesResource($this->transport);
        $this->members = new MembersResource($this->transport);
        $this->files = new FilesResource($this->transport);
        $this->calendarEvents = new CalendarEventsResource($this->transport);
        $this->calendar = new CalendarResource($this->transport);
        $this->meetings = new MeetingsResource($this->transport);
        $this->webhooks = new WebhooksResource($this->transport);
        $this->agents = new AgentsResource($this->transport);
    }

    public function baseUrl(): string
    {
        return $this->transport->baseUrl();
    }

    /**
     * The OpenAPI 3.1 document the server publishes (public; no token needed).
     *
     * @return array<string, mixed>|list<mixed>|null
     */
    public function openapi(?RequestOptions $options = null): ?array
    {
        return $this->transport->request('GET', '/openapi.json', [], null, $options);
    }

    /**
     * Calls an endpoint this SDK version does not wrap yet, with the same
     * authentication, retries and errors. $path is relative to the base URL.
     *
     * @param array<string, scalar|null> $query
     *
     * @return array<string, mixed>|list<mixed>|null
     */
    public function request(string $method, string $path, array $query = [], mixed $body = null, ?RequestOptions $options = null): ?array
    {
        return $this->transport->request(strtoupper($method), $path, $query, $body, $options);
    }

    /** var_dump() and print_r() never show the token. */
    public function __debugInfo(): array
    {
        return $this->transport->__debugInfo();
    }

    /** The client is not serialisable, so a token cannot end up in a cache or session. */
    public function __serialize(): array
    {
        throw new LogicException('The IMPROJ client cannot be serialised.');
    }

    /**
     * @template T
     *
     * @param callable(): T $find
     *
     * @return T
     */
    private static function discover(string $what, callable $find): mixed
    {
        $message = 'No HTTP client given: pass a PSR-18 client and PSR-17 factories, or install php-http/discovery with an implementation such as guzzlehttp/guzzle';
        if (!class_exists(Psr18ClientDiscovery::class)) {
            throw new ConfigurationException($message . '.', 'CONFIGURATION');
        }
        try {
            return $find();
        } catch (\Throwable $e) {
            throw new ConfigurationException(sprintf('%s (%s found none).', $message, $what), 'CONFIGURATION', previous: $e);
        }
    }
}
