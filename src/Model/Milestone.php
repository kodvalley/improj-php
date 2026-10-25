<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Model;

use Kodvalley\Improj\Internal\Data;

/**
 * A project milestone.
 */
final class Milestone extends Model
{
    public readonly int $id;

    public readonly int $projectId;

    public readonly string $title;

    public readonly ?string $description;

    public readonly ?string $dueDate;

    public readonly bool $completed;

    public readonly ?string $completedAt;

    public readonly ?string $color;

    public readonly ?string $createdAt;

    public readonly ?string $updatedAt;

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(array $data)
    {
        parent::__construct($data);
        $this->id = Data::int($data, 'id');
        $this->projectId = Data::int($data, 'project_id');
        $this->title = Data::string($data, 'title');
        $this->description = Data::stringOrNull($data, 'description');
        $this->dueDate = Data::stringOrNull($data, 'due_date');
        $this->completed = Data::bool($data, 'completed');
        $this->completedAt = Data::stringOrNull($data, 'completed_at');
        $this->color = Data::stringOrNull($data, 'color');
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
