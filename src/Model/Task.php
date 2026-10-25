<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Model;

use Kodvalley\Improj\Internal\Data;

/**
 * A task. status is todo, in_progress, stuck or done.
 */
final class Task extends Model
{
    public readonly int $id;

    public readonly int $projectId;

    public readonly string $title;

    public readonly ?string $description;

    public readonly string $status;

    public readonly bool $isReview;

    public readonly ?string $priority;

    public readonly ?string $dueDate;

    public readonly ?string $startDate;

    public readonly ?int $ownerUserId;

    /** @var list<TaskHelper> */
    public readonly array $helpers;

    public readonly ?int $partnerId;

    public readonly ?int $workPackageId;

    public readonly ?int $activityId;

    public readonly bool $canEdit;

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
        $this->status = Data::string($data, 'status');
        $this->isReview = Data::bool($data, 'is_review');
        $this->priority = Data::stringOrNull($data, 'priority');
        $this->dueDate = Data::stringOrNull($data, 'due_date');
        $this->startDate = Data::stringOrNull($data, 'start_date');
        $this->ownerUserId = Data::intOrNull($data, 'owner_user_id');
        $this->helpers = array_map(static fn(array $x): TaskHelper => TaskHelper::fromArray($x), Data::objects($data, 'helpers'));
        $this->partnerId = Data::intOrNull($data, 'partner_id');
        $this->workPackageId = Data::intOrNull($data, 'work_package_id');
        $this->activityId = Data::intOrNull($data, 'activity_id');
        $this->canEdit = Data::bool($data, 'can_edit');
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
