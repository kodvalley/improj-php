<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Model;

use Kodvalley\Improj\Internal\Data;

/**
 * A work package of a project.
 */
final class WorkPackage extends Model
{
    public readonly int $id;

    public readonly int $projectId;

    public readonly ?string $number;

    public readonly string $title;

    public readonly ?string $description;

    public readonly ?string $startDate;

    public readonly ?string $endDate;

    public readonly ?string $status;

    public readonly ?string $color;

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(array $data)
    {
        parent::__construct($data);
        $this->id = Data::int($data, 'id');
        $this->projectId = Data::int($data, 'project_id');
        $this->number = Data::stringOrNull($data, 'number');
        $this->title = Data::string($data, 'title');
        $this->description = Data::stringOrNull($data, 'description');
        $this->startDate = Data::stringOrNull($data, 'start_date');
        $this->endDate = Data::stringOrNull($data, 'end_date');
        $this->status = Data::stringOrNull($data, 'status');
        $this->color = Data::stringOrNull($data, 'color');
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
