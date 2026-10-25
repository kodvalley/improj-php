<?php

declare(strict_types=1);

namespace Kodvalley\Improj;

use ArrayIterator;
use Closure;
use Countable;
use Generator;
use IteratorAggregate;
use Kodvalley\Improj\Exception\ImprojException;

/**
 * One page of a list. Iterate it for this page's items, call nextPage() for
 * the following page, or autoPaging() to walk every item of every page:
 *
 *     foreach ($improj->tasks->list(42)->autoPaging() as $task) { ... }
 *
 * @template T
 *
 * @implements IteratorAggregate<int, T>
 */
final class Page implements IteratorAggregate, Countable
{
    /**
     * @param list<T>                $data
     * @param Closure(string): Page<T> $fetchNext
     */
    public function __construct(
        public readonly array $data,
        public readonly ?string $nextCursor,
        private readonly Closure $fetchNext,
    ) {}

    public function hasNextPage(): bool
    {
        return $this->nextCursor !== null && $this->nextCursor !== '';
    }

    /**
     * @return Page<T>
     */
    public function nextPage(): self
    {
        if (!$this->hasNextPage()) {
            throw new ImprojException('This is the last page.', 'NO_MORE_PAGES');
        }

        return ($this->fetchNext)((string) $this->nextCursor);
    }

    /**
     * Every item from this page on, fetching pages lazily. Stops with an error
     * if the API ever repeats a cursor.
     *
     * @return Generator<int, T>
     */
    public function autoPaging(): Generator
    {
        $page = $this;
        $seen = [];
        while (true) {
            foreach ($page->data as $item) {
                yield $item;
            }
            if (!$page->hasNextPage()) {
                return;
            }
            $cursor = (string) $page->nextCursor;
            if (isset($seen[$cursor])) {
                throw new ImprojException('The API returned a cursor twice; stopping.', 'PAGINATION_LOOP');
            }
            $seen[$cursor] = true;
            $page = $page->nextPage();
        }
    }

    /**
     * @return ArrayIterator<int, T>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->data);
    }

    public function count(): int
    {
        return \count($this->data);
    }
}
