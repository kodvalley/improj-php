<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Model;

use Kodvalley\Improj\Internal\Data;

/**
 * An item of your own calendar (GET /calendar).
 */
final class CalendarItem extends Model
{
    public readonly string $id;

    public readonly ?string $source;

    public readonly ?string $title;

    public readonly ?string $startDate;

    public readonly ?string $endDate;

    public readonly ?string $startTime;

    public readonly ?string $endTime;

    public readonly bool $allDay;

    public readonly ?string $timeZone;

    public readonly ?string $location;

    public readonly ?string $meetingUrl;

    public readonly ?string $status;

    public readonly ?ProjectRef $project;

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(array $data)
    {
        parent::__construct($data);
        $this->id = Data::string($data, 'id');
        $this->source = Data::stringOrNull($data, 'source');
        $this->title = Data::stringOrNull($data, 'title');
        $this->startDate = Data::stringOrNull($data, 'start_date');
        $this->endDate = Data::stringOrNull($data, 'end_date');
        $this->startTime = Data::stringOrNull($data, 'start_time');
        $this->endTime = Data::stringOrNull($data, 'end_time');
        $this->allDay = Data::bool($data, 'all_day');
        $this->timeZone = Data::stringOrNull($data, 'time_zone');
        $this->location = Data::stringOrNull($data, 'location');
        $this->meetingUrl = Data::stringOrNull($data, 'meeting_url');
        $this->status = Data::stringOrNull($data, 'status');
        $this->project = ($o = Data::objectOrNull($data, 'project')) === null ? null : ProjectRef::fromArray($o);
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
