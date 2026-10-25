<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Model;

/**
 * Base of the typed API objects. The API adds fields over time: typed
 * properties cover the documented ones, toArray() returns everything the API
 * sent, including fields this SDK version does not know yet.
 */
abstract class Model
{
    /**
     * @param array<string, mixed> $raw
     */
    protected function __construct(private readonly array $raw) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->raw;
    }
}
