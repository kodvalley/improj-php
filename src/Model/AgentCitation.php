<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Model;

use Kodvalley\Improj\Internal\Data;

/**
 * A source an agent item cites.
 */
final class AgentCitation extends Model
{
    public readonly string $label;

    public readonly string $path;

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(array $data)
    {
        parent::__construct($data);
        $this->label = Data::string($data, 'label');
        $this->path = Data::string($data, 'path');
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws \UnexpectedValueException when a documented field is missing or has the wrong type
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }
}
