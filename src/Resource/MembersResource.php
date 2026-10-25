<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Resource;

use Generator;
use Kodvalley\Improj\Model\Member;
use Kodvalley\Improj\Page;
use Kodvalley\Improj\RequestOptions;

/**
 * Project members (scope members:read). Email addresses follow each person's privacy setting.
 */
final class MembersResource extends AbstractResource
{
    /**
     * @param array{limit?: int, cursor?: string} $params
     *
     * @return Page<Member>
     */
    public function list(int|string $projectId, array $params = [], ?RequestOptions $options = null): Page
    {
        return $this->page('/projects/' . self::id($projectId, 'projectId') . '/members', $params, Member::fromArray(...), $options);
    }

    /**
     * @param array{limit?: int} $params
     *
     * @return Generator<int, Member>
     */
    public function all(int|string $projectId, array $params = [], ?RequestOptions $options = null): Generator
    {
        return $this->list($projectId, $params, $options)->autoPaging();
    }
}
