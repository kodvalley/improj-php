<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Model;

use Kodvalley\Improj\Internal\Data;

/**
 * An activity of a project.
 */
final class Activity extends Model
{
    public readonly int $id;

    public readonly int $projectId;

    public readonly ?int $workPackageId;

    public readonly ?string $number;

    public readonly string $title;

    public readonly ?string $description;

    public readonly ?string $type;

    public readonly ?string $startDate;

    public readonly ?string $endDate;

    public readonly ?string $status;

    public readonly ?string $location;

    public readonly bool $isOnline;

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(array $data)
    {
        parent::__construct($data);
        $this->id = Data::int($data, 'id');
        $this->projectId = Data::int($data, 'project_id');
        $this->workPackageId = Data::intOrNull($data, 'work_package_id');
        $this->number = Data::stringOrNull($data, 'number');
        $this->title = Data::string($data, 'title');
        $this->description = Data::stringOrNull($data, 'description');
        $this->type = Data::stringOrNull($data, 'type');
        $this->startDate = Data::stringOrNull($data, 'start_date');
        $this->endDate = Data::stringOrNull($data, 'end_date');
        $this->status = Data::stringOrNull($data, 'status');
        $this->location = Data::stringOrNull($data, 'location');
        $this->isOnline = Data::bool($data, 'is_online');
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
