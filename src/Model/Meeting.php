<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Model;

use Kodvalley\Improj\Internal\Data;

/**
 * A project meeting with its notes.
 */
final class Meeting extends Model
{
    public readonly int $id;

    public readonly int $projectId;

    public readonly ?string $title;

    public readonly ?string $type;

    public readonly ?string $date;

    public readonly ?string $startTime;

    public readonly ?string $endTime;

    public readonly ?string $timeZone;

    public readonly ?string $location;

    public readonly bool $isOnline;

    public readonly ?string $meetingUrl;

    public readonly ?string $status;

    public readonly ?string $agenda;

    public readonly ?string $notes;

    public readonly ?string $decisions;

    public readonly ?string $actionItems;

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
        $this->title = Data::stringOrNull($data, 'title');
        $this->type = Data::stringOrNull($data, 'type');
        $this->date = Data::stringOrNull($data, 'date');
        $this->startTime = Data::stringOrNull($data, 'start_time');
        $this->endTime = Data::stringOrNull($data, 'end_time');
        $this->timeZone = Data::stringOrNull($data, 'time_zone');
        $this->location = Data::stringOrNull($data, 'location');
        $this->isOnline = Data::bool($data, 'is_online');
        $this->meetingUrl = Data::stringOrNull($data, 'meeting_url');
        $this->status = Data::stringOrNull($data, 'status');
        $this->agenda = Data::stringOrNull($data, 'agenda');
        $this->notes = Data::stringOrNull($data, 'notes');
        $this->decisions = Data::stringOrNull($data, 'decisions');
        $this->actionItems = Data::stringOrNull($data, 'action_items');
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
