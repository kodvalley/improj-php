<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Resource;

use Generator;
use Kodvalley\Improj\Model\Activity;
use Kodvalley\Improj\Model\Project;
use Kodvalley\Improj\Model\WorkPackage;
use Kodvalley\Improj\Page;
use Kodvalley\Improj\RequestOptions;

/**
 * Projects, their work packages and activities (scope projects:read).
 */
final class ProjectsResource extends AbstractResource
{
    /**
     * Projects the token reaches.
     *
     * @param array{limit?: int, cursor?: string} $params
     *
     * @return Page<Project>
     */
    public function list(array $params = [], ?RequestOptions $options = null): Page
    {
        return $this->page('/projects', $params, Project::fromArray(...), $options);
    }

    /**
     * Every project, fetching pages as needed.
     *
     * @param array{limit?: int} $params
     *
     * @return Generator<int, Project>
     */
    public function all(array $params = [], ?RequestOptions $options = null): Generator
    {
        return $this->list($params, $options)->autoPaging();
    }

    public function get(int|string $projectId, ?RequestOptions $options = null): Project
    {
        return $this->getOne('/projects/' . self::id($projectId, 'projectId'), Project::fromArray(...), $options);
    }

    /**
     * @param array{limit?: int, cursor?: string} $params
     *
     * @return Page<WorkPackage>
     */
    public function workPackages(int|string $projectId, array $params = [], ?RequestOptions $options = null): Page
    {
        return $this->page('/projects/' . self::id($projectId, 'projectId') . '/work-packages', $params, WorkPackage::fromArray(...), $options);
    }

    /**
     * @param array{limit?: int, cursor?: string, work_package_id?: int} $params
     *
     * @return Page<Activity>
     */
    public function activities(int|string $projectId, array $params = [], ?RequestOptions $options = null): Page
    {
        if (isset($params['work_package_id'])) {
            $params['work_package_id'] = (int) self::id($params['work_package_id'], 'work_package_id');
        }

        return $this->page('/projects/' . self::id($projectId, 'projectId') . '/activities', $params, Activity::fromArray(...), $options);
    }
}
