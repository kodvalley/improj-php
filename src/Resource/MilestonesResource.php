<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Resource;

use Generator;
use Kodvalley\Improj\Model\Milestone;
use Kodvalley\Improj\Page;
use Kodvalley\Improj\RequestOptions;

/**
 * Milestones (scopes milestones:read, milestones:write).
 *
 * @psalm-type MilestoneInput = array{title?: string, description?: string, due_date?: string|null, color?: string, completed?: bool}
 */
final class MilestonesResource extends AbstractResource
{
    /**
     * @param array{limit?: int, cursor?: string} $params
     *
     * @return Page<Milestone>
     */
    public function list(int|string $projectId, array $params = [], ?RequestOptions $options = null): Page
    {
        return $this->page($this->base($projectId), $params, Milestone::fromArray(...), $options);
    }

    /**
     * @param array{limit?: int} $params
     *
     * @return Generator<int, Milestone>
     */
    public function all(int|string $projectId, array $params = [], ?RequestOptions $options = null): Generator
    {
        return $this->list($projectId, $params, $options)->autoPaging();
    }

    public function get(int|string $projectId, int|string $milestoneId, ?RequestOptions $options = null): Milestone
    {
        return $this->getOne($this->base($projectId) . '/' . self::id($milestoneId, 'milestoneId'), Milestone::fromArray(...), $options);
    }

    /**
     * @param MilestoneInput $input title required; color as #RRGGBB
     */
    public function create(int|string $projectId, array $input, ?RequestOptions $options = null): Milestone
    {
        return $this->write('POST', $this->base($projectId), $input, Milestone::fromArray(...), $options);
    }

    /**
     * @param MilestoneInput $input
     */
    public function update(int|string $projectId, int|string $milestoneId, array $input, ?RequestOptions $options = null): Milestone
    {
        return $this->write('PATCH', $this->base($projectId) . '/' . self::id($milestoneId, 'milestoneId'), $input, Milestone::fromArray(...), $options);
    }

    public function delete(int|string $projectId, int|string $milestoneId, ?RequestOptions $options = null): void
    {
        $this->remove($this->base($projectId) . '/' . self::id($milestoneId, 'milestoneId'), $options);
    }

    private function base(int|string $projectId): string
    {
        return '/projects/' . self::id($projectId, 'projectId') . '/milestones';
    }
}
