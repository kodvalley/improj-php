<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Resource;

use Generator;
use Kodvalley\Improj\Model\Task;
use Kodvalley\Improj\Page;
use Kodvalley\Improj\RequestOptions;

/**
 * Tasks (scopes tasks:read, tasks:write).
 *
 * @psalm-type TaskInput = array{
 *     title?: string,
 *     description?: string,
 *     status?: 'todo'|'in_progress'|'stuck'|'done',
 *     priority?: 'low'|'medium'|'high'|'urgent',
 *     due_date?: string|null,
 *     start_date?: string|null,
 *     owner_user_id?: int|null,
 *     helper_member_ids?: list<int>,
 *     partner_id?: int|null,
 *     work_package_id?: int|null,
 *     activity_id?: int|null
 * }
 */
final class TasksResource extends AbstractResource
{
    /**
     * @param array{limit?: int, cursor?: string, status?: 'todo'|'in_progress'|'stuck'|'done'} $params
     *
     * @return Page<Task>
     */
    public function list(int|string $projectId, array $params = [], ?RequestOptions $options = null): Page
    {
        return $this->page($this->base($projectId), $params, Task::fromArray(...), $options);
    }

    /**
     * @param array{limit?: int, status?: 'todo'|'in_progress'|'stuck'|'done'} $params
     *
     * @return Generator<int, Task>
     */
    public function all(int|string $projectId, array $params = [], ?RequestOptions $options = null): Generator
    {
        return $this->list($projectId, $params, $options)->autoPaging();
    }

    public function get(int|string $projectId, int|string $taskId, ?RequestOptions $options = null): Task
    {
        return $this->getOne($this->base($projectId) . '/' . self::id($taskId, 'taskId'), Task::fromArray(...), $options);
    }

    /**
     * Creates a task (title required). Sends an Idempotency-Key.
     *
     * @param TaskInput $input
     */
    public function create(int|string $projectId, array $input, ?RequestOptions $options = null): Task
    {
        return $this->write('POST', $this->base($projectId), $input, Task::fromArray(...), $options);
    }

    /**
     * Changes only the fields you send; null clears a nullable field.
     *
     * @param TaskInput $input
     */
    public function update(int|string $projectId, int|string $taskId, array $input, ?RequestOptions $options = null): Task
    {
        return $this->write('PATCH', $this->base($projectId) . '/' . self::id($taskId, 'taskId'), $input, Task::fromArray(...), $options);
    }

    public function delete(int|string $projectId, int|string $taskId, ?RequestOptions $options = null): void
    {
        $this->remove($this->base($projectId) . '/' . self::id($taskId, 'taskId'), $options);
    }

    private function base(int|string $projectId): string
    {
        return '/projects/' . self::id($projectId, 'projectId') . '/tasks';
    }
}
