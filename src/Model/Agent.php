<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Model;

use Kodvalley\Improj\Internal\Data;

/**
 * An Enterprise AI agent you report to (camelCase fields, as in the earlier agent API).
 */
final class Agent extends Model
{
    public readonly int $id;

    public readonly string $name;

    public readonly AgentOrganization $organization;

    public readonly string $status;

    public readonly ?string $pausedReason;

    public readonly bool $principal;

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(array $data)
    {
        parent::__construct($data);
        $this->id = Data::int($data, 'id');
        $this->name = Data::string($data, 'name');
        $this->organization = AgentOrganization::fromArray(Data::object($data, 'organization'));
        $this->status = Data::string($data, 'status');
        $this->pausedReason = Data::stringOrNull($data, 'pausedReason');
        $this->principal = Data::bool($data, 'principal');
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
