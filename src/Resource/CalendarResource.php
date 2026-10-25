<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Resource;

use Kodvalley\Improj\Exception\ConfigurationException;
use Kodvalley\Improj\Model\CalendarItem;
use Kodvalley\Improj\RequestOptions;

/**
 * Your own calendar across projects (GET /calendar, scope calendar:read).
 */
final class CalendarResource extends AbstractResource
{
    /**
     * Items between two days (YYYY-MM-DD, at most 366 days apart). Not paginated.
     *
     * @return list<CalendarItem>
     */
    public function list(string $from, string $to, ?RequestOptions $options = null): array
    {
        foreach (['from' => $from, 'to' => $to] as $name => $day) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) !== 1) {
                throw new ConfigurationException(sprintf('%s must be a day such as 2026-11-02.', $name), 'CONFIGURATION');
            }
        }

        return $this->mapList($this->transport->request('GET', '/calendar', ['from' => $from, 'to' => $to], null, $options), CalendarItem::fromArray(...));
    }
}
