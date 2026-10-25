<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Resource;

use Closure;
use Kodvalley\Improj\Exception\ConfigurationException;
use Kodvalley\Improj\Exception\ImprojException;
use Kodvalley\Improj\Http\Transport;
use Kodvalley\Improj\Internal\Data;
use Kodvalley\Improj\Page;
use Kodvalley\Improj\RequestOptions;
use UnexpectedValueException;

/**
 * Shared plumbing of the resource classes.
 */
abstract class AbstractResource
{
    public function __construct(protected readonly Transport $transport) {}

    /** A path segment from an id: positive integers only, so nothing can escape the path. */
    protected static function id(int|string $value, string $name): string
    {
        if (\is_int($value) && $value > 0) {
            return (string) $value;
        }
        if (\is_string($value) && preg_match('/^[1-9]\d{0,15}$/', $value) === 1) {
            return $value;
        }
        throw new ConfigurationException(sprintf('%s must be a positive integer id.', $name), 'CONFIGURATION');
    }

    /**
     * GET an object and map its `data`.
     *
     * @template T
     *
     * @param Closure(array<string, mixed>): T $map
     * @param array<string, scalar|null>        $query
     *
     * @return T
     */
    protected function getOne(string $path, Closure $map, ?RequestOptions $options = null, array $query = []): mixed
    {
        return $this->mapData($this->transport->request('GET', $path, $query, null, $options), $map);
    }

    /**
     * POST or PATCH JSON and map the answer's `data`.
     *
     * @template T
     *
     * @param array<string, mixed>|null        $body
     * @param Closure(array<string, mixed>): T $map
     *
     * @return T
     */
    protected function write(string $method, string $path, ?array $body, Closure $map, ?RequestOptions $options = null): mixed
    {
        return $this->mapData($this->transport->request($method, $path, [], $body ?? null, $options), $map);
    }

    protected function remove(string $path, ?RequestOptions $options = null): void
    {
        $this->transport->request('DELETE', $path, [], null, $options);
    }

    /**
     * A page of a list; nextPage() fetches the following one with the same filters.
     *
     * @template T
     *
     * @param array<string, scalar|null>        $params
     * @param Closure(array<string, mixed>): T $map
     *
     * @return Page<T>
     */
    protected function page(string $path, array $params, Closure $map, ?RequestOptions $options = null, string $cursorField = 'next_cursor'): Page
    {
        $fetch = function (?string $cursor) use (&$fetch, $path, $params, $map, $options, $cursorField): Page {
            $query = $params;
            if ($cursor !== null) {
                $query['cursor'] = $cursor;
            }
            $body = $this->transport->request('GET', $path, $query, null, $options);
            try {
                if ($body === null || array_is_list($body)) {
                    throw new UnexpectedValueException('A list answer must be an object.');
                }
                $items = array_map($map, Data::objects($body, 'data'));
                $next = $body[$cursorField] ?? ($body['next_cursor'] ?? null);
                if ($next !== null && !\is_string($next) && !\is_int($next)) {
                    throw new UnexpectedValueException('next_cursor must be a string or null.');
                }
            } catch (UnexpectedValueException $e) {
                throw new ImprojException('The list answer is malformed: ' . $e->getMessage(), 'INVALID_RESPONSE', previous: $e);
            }
            /** @var Closure(string): Page<T> $fetch */
            return new Page($items, $next === null || $next === '' ? null : (string) $next, Closure::fromCallable($fetch));
        };

        /** @var Page<T> */
        return $fetch(isset($params['cursor']) && \is_string($params['cursor']) ? $params['cursor'] : null);
    }

    /**
     * @template T
     *
     * @param array<string, mixed>|list<mixed>|null $body
     * @param Closure(array<string, mixed>): T      $map
     *
     * @return T
     */
    private function mapData(?array $body, Closure $map): mixed
    {
        try {
            if ($body === null || array_is_list($body)) {
                throw new UnexpectedValueException('The answer has no data object.');
            }

            return $map(Data::object($body, 'data'));
        } catch (UnexpectedValueException $e) {
            throw new ImprojException('The answer is malformed: ' . $e->getMessage(), 'INVALID_RESPONSE', previous: $e);
        }
    }

    /**
     * The list in `data` of an answer that is not paginated.
     *
     * @template T
     *
     * @param array<string, mixed>|list<mixed>|null $body
     * @param Closure(array<string, mixed>): T      $map
     *
     * @return list<T>
     */
    protected function mapList(?array $body, Closure $map): array
    {
        try {
            if ($body === null || array_is_list($body)) {
                throw new UnexpectedValueException('The answer has no data list.');
            }

            return array_map($map, Data::objects($body, 'data'));
        } catch (UnexpectedValueException $e) {
            throw new ImprojException('The answer is malformed: ' . $e->getMessage(), 'INVALID_RESPONSE', previous: $e);
        }
    }
}
