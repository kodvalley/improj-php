<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Model;

use Kodvalley\Improj\Internal\Data;

/**
 * A project calendar event. time_zone is the IANA zone of the wall-clock times.
 */
final class CalendarEvent extends Model
{
    public readonly int $id;

    public readonly int $projectId;

    public readonly string $title;

    public readonly ?string $description;

    public readonly ?string $type;

    public readonly ?string $startDate;

    public readonly ?string $endDate;

    public readonly ?string $startTime;

    public readonly ?string $endTime;

    public readonly bool $allDay;

    public readonly ?string $timeZone;

    public readonly ?string $location;

    public readonly bool $isOnline;

    public readonly ?string $meetingUrl;

    public readonly ?string $status;

    /** @var list<string> */
    public readonly array $attendees;

    public readonly ?string $recurrence;

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
        $this->type = Data::stringOrNull($data, 'type');
        $this->startDate = Data::stringOrNull($data, 'start_date');
        $this->endDate = Data::stringOrNull($data, 'end_date');
        $this->startTime = Data::stringOrNull($data, 'start_time');
        $this->endTime = Data::stringOrNull($data, 'end_time');
        $this->allDay = Data::bool($data, 'all_day');
        $this->timeZone = Data::stringOrNull($data, 'time_zone');
        $this->location = Data::stringOrNull($data, 'location');
        $this->isOnline = Data::bool($data, 'is_online');
        $this->meetingUrl = Data::stringOrNull($data, 'meeting_url');
        $this->status = Data::stringOrNull($data, 'status');
        $this->attendees = Data::strings($data, 'attendees');
        $this->recurrence = Data::stringOrNull($data, 'recurrence');
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
