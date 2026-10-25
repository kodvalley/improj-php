<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Resource;

use Generator;
use Kodvalley\Improj\Model\Meeting;
use Kodvalley\Improj\Page;
use Kodvalley\Improj\RequestOptions;

/**
 * Project meetings and their notes (scope meetings:read).
 */
final class MeetingsResource extends AbstractResource
{
    /**
     * @param array{limit?: int, cursor?: string} $params
     *
     * @return Page<Meeting>
     */
    public function list(int|string $projectId, array $params = [], ?RequestOptions $options = null): Page
    {
        return $this->page('/projects/' . self::id($projectId, 'projectId') . '/meetings', $params, Meeting::fromArray(...), $options);
    }

    /**
     * @param array{limit?: int} $params
     *
     * @return Generator<int, Meeting>
     */
    public function all(int|string $projectId, array $params = [], ?RequestOptions $options = null): Generator
    {
        return $this->list($projectId, $params, $options)->autoPaging();
    }

    public function get(int|string $projectId, int|string $meetingId, ?RequestOptions $options = null): Meeting
    {
        return $this->getOne('/projects/' . self::id($projectId, 'projectId') . '/meetings/' . self::id($meetingId, 'meetingId'), Meeting::fromArray(...), $options);
    }
}
