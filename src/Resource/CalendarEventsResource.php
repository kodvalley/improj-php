<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Resource;

use Generator;
use Kodvalley\Improj\Model\CalendarEvent;
use Kodvalley\Improj\Page;
use Kodvalley\Improj\RequestOptions;

/**
 * Project calendar events (scopes calendar:read, calendar:write). A repeating
 * event is changed or deleted as a whole series.
 *
 * @psalm-type EventInput = array{
 *     title?: string,
 *     start_date?: string,
 *     description?: string,
 *     type?: string,
 *     end_date?: string,
 *     start_time?: string,
 *     end_time?: string,
 *     time_zone?: string,
 *     location?: string,
 *     is_online?: bool,
 *     meeting_url?: string,
 *     status?: 'scheduled'|'confirmed'|'cancelled'|'completed',
 *     attendees?: list<string>
 * }
 */
final class CalendarEventsResource extends AbstractResource
{
    /**
     * @param array{limit?: int, cursor?: string, from?: string, to?: string} $params
     *
     * @return Page<CalendarEvent>
     */
    public function list(int|string $projectId, array $params = [], ?RequestOptions $options = null): Page
    {
        return $this->page($this->base($projectId), $params, CalendarEvent::fromArray(...), $options);
    }

    /**
     * @param array{limit?: int, from?: string, to?: string} $params
     *
     * @return Generator<int, CalendarEvent>
     */
    public function all(int|string $projectId, array $params = [], ?RequestOptions $options = null): Generator
    {
        return $this->list($projectId, $params, $options)->autoPaging();
    }

    public function get(int|string $projectId, int|string $eventId, ?RequestOptions $options = null): CalendarEvent
    {
        return $this->getOne($this->base($projectId) . '/' . self::id($eventId, 'eventId'), CalendarEvent::fromArray(...), $options);
    }

    /**
     * @param EventInput $input title and start_date (YYYY-MM-DD) required
     */
    public function create(int|string $projectId, array $input, ?RequestOptions $options = null): CalendarEvent
    {
        return $this->write('POST', $this->base($projectId), $input, CalendarEvent::fromArray(...), $options);
    }

    /**
     * @param EventInput $input
     */
    public function update(int|string $projectId, int|string $eventId, array $input, ?RequestOptions $options = null): CalendarEvent
    {
        return $this->write('PATCH', $this->base($projectId) . '/' . self::id($eventId, 'eventId'), $input, CalendarEvent::fromArray(...), $options);
    }

    public function delete(int|string $projectId, int|string $eventId, ?RequestOptions $options = null): void
    {
        $this->remove($this->base($projectId) . '/' . self::id($eventId, 'eventId'), $options);
    }

    private function base(int|string $projectId): string
    {
        return '/projects/' . self::id($projectId, 'projectId') . '/calendar-events';
    }
}
