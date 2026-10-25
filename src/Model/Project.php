<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Model;

use Kodvalley\Improj\Internal\Data;

/**
 * A project the token reaches.
 */
final class Project extends Model
{
    public readonly int $id;

    public readonly string $name;

    public readonly ?string $acronym;

    public readonly ?string $color;

    public readonly ?string $type;

    public readonly ?string $status;

    public readonly ?string $startDate;

    public readonly ?string $endDate;

    public readonly ?string $timeZone;

    public readonly ?int $organizationId;

    public readonly string $role;

    public readonly bool $archived;

    public readonly ?string $createdAt;

    public readonly ?string $updatedAt;

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(array $data)
    {
        parent::__construct($data);
        $this->id = Data::int($data, 'id');
        $this->name = Data::string($data, 'name');
        $this->acronym = Data::stringOrNull($data, 'acronym');
        $this->color = Data::stringOrNull($data, 'color');
        $this->type = Data::stringOrNull($data, 'type');
        $this->status = Data::stringOrNull($data, 'status');
        $this->startDate = Data::stringOrNull($data, 'start_date');
        $this->endDate = Data::stringOrNull($data, 'end_date');
        $this->timeZone = Data::stringOrNull($data, 'time_zone');
        $this->organizationId = Data::intOrNull($data, 'organization_id');
        $this->role = Data::string($data, 'role');
        $this->archived = Data::bool($data, 'archived');
        $this->createdAt = Data::stringOrNull($data, 'created_at');
        $this->updatedAt = Data::stringOrNull($data, 'updated_at');
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
