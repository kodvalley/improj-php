<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Tests;

use Closure;
use Kodvalley\Improj\Exception\ConfigurationException;
use Kodvalley\Improj\Exception\ImprojException;
use Kodvalley\Improj\Improj;
use Kodvalley\Improj\Model\Task;
use Kodvalley\Improj\Tests\Support\MockClient;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Contract test: every operation of tests/fixtures/openapi.json is reachable
 * with the right method and path, and the SDK calls nothing the spec lacks.
 */
final class ResourcesTest extends TestCase
{
    private const UPLOAD_ID = 'upl_ABCDEFGHIJKLMNOPQRSTUVWXYZ012345';

    /**
     * @return array<string, Closure(Improj): mixed>
     */
    private static function calls(): array
    {
        return [
            'getMe' => static fn(Improj $c) => $c->me->get(),
            'listProjects' => static fn(Improj $c) => $c->projects->list(),
            'getProject' => static fn(Improj $c) => $c->projects->get(1),
            'listWorkPackages' => static fn(Improj $c) => $c->projects->workPackages(1),
            'listActivities' => static fn(Improj $c) => $c->projects->activities(1, ['work_package_id' => 2]),
            'listTasks' => static fn(Improj $c) => $c->tasks->list(1, ['status' => 'todo']),
            'createTask' => static fn(Improj $c) => $c->tasks->create(1, ['title' => 'T']),
            'getTask' => static fn(Improj $c) => $c->tasks->get(1, 2),
            'updateTask' => static fn(Improj $c) => $c->tasks->update(1, 2, ['status' => 'done']),
            'deleteTask' => static fn(Improj $c) => $c->tasks->delete(1, 2),
            'listMilestones' => static fn(Improj $c) => $c->milestones->list(1),
            'createMilestone' => static fn(Improj $c) => $c->milestones->create(1, ['title' => 'M']),
            'getMilestone' => static fn(Improj $c) => $c->milestones->get(1, 2),
            'updateMilestone' => static fn(Improj $c) => $c->milestones->update(1, 2, ['completed' => true]),
            'deleteMilestone' => static fn(Improj $c) => $c->milestones->delete(1, 2),
            'listMembers' => static fn(Improj $c) => $c->members->list(1),
            'listFiles' => static fn(Improj $c) => $c->files->list(1, ['parent_id' => 'root']),
            'getFile' => static fn(Improj $c) => $c->files->get(1, 2),
            'getFileDownloadLink' => static fn(Improj $c) => $c->files->downloadLink(1, 2),
            'createUpload' => static fn(Improj $c) => $c->files->createUpload(1, ['name' => 'a.pdf', 'size' => 3, 'mime_type' => 'application/pdf']),
            'sendUpload' => static fn(Improj $c) => $c->files->sendUpload(self::UPLOAD_ID, 'abc'),
            'listCalendarEvents' => static fn(Improj $c) => $c->calendarEvents->list(1, ['from' => '2026-01-01']),
            'createCalendarEvent' => static fn(Improj $c) => $c->calendarEvents->create(1, ['title' => 'E', 'start_date' => '2026-11-02']),
            'getCalendarEvent' => static fn(Improj $c) => $c->calendarEvents->get(1, 2),
            'updateCalendarEvent' => static fn(Improj $c) => $c->calendarEvents->update(1, 2, ['status' => 'cancelled']),
            'deleteCalendarEvent' => static fn(Improj $c) => $c->calendarEvents->delete(1, 2),
            'listMeetings' => static fn(Improj $c) => $c->meetings->list(1),
            'getMeeting' => static fn(Improj $c) => $c->meetings->get(1, 2),
            'listWebhooks' => static fn(Improj $c) => $c->webhooks->list(),
            'createWebhook' => static fn(Improj $c) => $c->webhooks->create(['url' => 'https://hooks.example.com/x', 'description' => 'CI', 'events' => ['task.created']]),
            'getWebhook' => static fn(Improj $c) => $c->webhooks->get(3),
            'updateWebhook' => static fn(Improj $c) => $c->webhooks->update(3, ['enabled' => true]),
            'deleteWebhook' => static fn(Improj $c) => $c->webhooks->delete(3),
            'testWebhook' => static fn(Improj $c) => $c->webhooks->test(3),
            'listWebhookDeliveries' => static fn(Improj $c) => $c->webhooks->deliveries(3),
            'listMyCalendar' => static fn(Improj $c) => $c->calendar->list('2026-01-01', '2026-01-31'),
            'listAgents' => static fn(Improj $c) => $c->agents->list(),
            'listAgentItems' => static fn(Improj $c) => $c->agents->items(4, ['limit' => 10]),
            'postAgentNote' => static fn(Improj $c) => $c->agents->postNote(4, ['kind' => 'question', 'text' => 'Status?']),
            'getOpenApi' => static fn(Improj $c) => $c->openapi(),
        ];
    }

    /**
     * @return list<array{string, string, string}>
     */
    private static function operations(): array
    {
        $spec = json_decode((string) file_get_contents(__DIR__ . '/fixtures/openapi.json'), true, 512, \JSON_THROW_ON_ERROR);
        \assert(\is_array($spec) && \is_array($spec['paths']));
        $ops = [];
        foreach ($spec['paths'] as $path => $item) {
            \assert(\is_array($item));
            foreach (['get', 'post', 'put', 'patch', 'delete'] as $method) {
                if (isset($item[$method]) && \is_array($item[$method]) && \is_string($item[$method]['operationId'] ?? null)) {
                    $ops[] = [$item[$method]['operationId'], strtoupper($method), (string) $path];
                }
            }
        }

        return $ops;
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function operationProvider(): iterable
    {
        foreach (self::operations() as $op) {
            yield $op[0] => $op;
        }
    }

    public function testEveryOperationIsImplementedAndNothingElse(): void
    {
        $ids = array_map(static fn(array $o): string => $o[0], self::operations());
        sort($ids);
        $implemented = array_keys(self::calls());
        sort($implemented);
        self::assertSame($ids, $implemented);
    }

    #[DataProvider('operationProvider')]
    public function testOperation(string $id, string $method, string $path): void
    {
        $m = new MockClient(static fn(RequestInterface $r): ResponseInterface => self::answer($r));
        (self::calls()[$id])($m->client());
        $r = $m->requests[0];
        self::assertSame($method, $r->getMethod());
        $pattern = '#^/api/v1' . preg_replace('/\\\\\{[a-z_]+\\\\\}/', '[^/]+', preg_quote($path, '#')) . '$#';
        self::assertMatchesRegularExpression($pattern, $r->getUri()->getPath());
        if ($method === 'POST') {
            self::assertNotSame('', $r->getHeaderLine('Idempotency-Key'));
        }
    }

    /** Minimal valid answers for every operation. */
    private static function answer(RequestInterface $r): ResponseInterface
    {
        $path = $r->getUri()->getPath();
        $json = static fn(int $s, mixed $b): ResponseInterface => new Response($s, ['Content-Type' => 'application/json'], json_encode($b, \JSON_THROW_ON_ERROR));
        if ($r->getMethod() === 'DELETE') {
            return new Response(204);
        }
        $object = match (true) {
            str_ends_with($path, '/me') => ['id' => 1, 'name' => null, 'email' => null, 'avatar_url' => null, 'time_zone' => null, 'token' => ['id' => 1, 'kind' => 'pat', 'scopes' => [], 'organization_id' => null, 'expires_at' => null]],
            (bool) preg_match('#/tasks/\d+$#', $path) || (str_ends_with($path, '/tasks') && $r->getMethod() === 'POST') => self::task(),
            (bool) preg_match('#/milestones/\d+$#', $path) || (str_ends_with($path, '/milestones') && $r->getMethod() === 'POST') => ['id' => 2, 'project_id' => 1, 'title' => 'M', 'description' => null, 'due_date' => null, 'completed' => false, 'completed_at' => null, 'color' => null, 'created_at' => null, 'updated_at' => null],
            (bool) preg_match('#/projects/\d+$#', $path) => ['id' => 1, 'name' => 'P', 'acronym' => null, 'color' => null, 'type' => null, 'status' => null, 'start_date' => null, 'end_date' => null, 'time_zone' => null, 'organization_id' => null, 'role' => 'owner', 'archived' => false, 'created_at' => null, 'updated_at' => null],
            str_ends_with($path, '/download') => ['url' => 'https://files.example/x', 'expires_at' => '2026-10-24T10:05:00Z'],
            str_ends_with($path, '/uploads') => ['id' => self::UPLOAD_ID, 'upload_url' => 'https://improj.com/api/v1/uploads/' . self::UPLOAD_ID, 'method' => 'PUT', 'max_bytes' => 3, 'expires_at' => '2026-10-24T10:15:00Z'],
            str_contains($path, '/uploads/') || (bool) preg_match('#/files/\d+$#', $path) => ['id' => 9, 'project_id' => 1, 'name' => 'a.pdf', 'type' => 'upload', 'is_folder' => false, 'parent_id' => null, 'mime_type' => 'application/pdf', 'size' => 3, 'visibility' => null, 'downloadable' => true, 'created_at' => null, 'updated_at' => null],
            (bool) preg_match('#/calendar-events/\d+$#', $path) || (str_ends_with($path, '/calendar-events') && $r->getMethod() === 'POST') => ['id' => 2, 'project_id' => 1, 'title' => 'E', 'description' => null, 'type' => null, 'start_date' => '2026-11-02', 'end_date' => null, 'start_time' => null, 'end_time' => null, 'all_day' => true, 'time_zone' => null, 'location' => null, 'is_online' => false, 'meeting_url' => null, 'status' => null, 'attendees' => [], 'recurrence' => null, 'created_at' => null, 'updated_at' => null],
            (bool) preg_match('#/meetings/\d+$#', $path) => ['id' => 2, 'project_id' => 1, 'title' => null, 'type' => null, 'date' => null, 'start_time' => null, 'end_time' => null, 'time_zone' => null, 'location' => null, 'is_online' => false, 'meeting_url' => null, 'status' => null, 'agenda' => null, 'notes' => null, 'decisions' => null, 'action_items' => null, 'created_at' => null, 'updated_at' => null],
            str_ends_with($path, '/test') => ['status' => 'delivered', 'response_code' => 200, 'error' => null],
            $path === '/api/v1/webhooks' && $r->getMethod() === 'POST' => ['endpoint' => self::endpoint(), 'secret' => 'whsec_TEST_ONLY_not_a_real_secret'],
            (bool) preg_match('#/webhooks/\d+$#', $path) => self::endpoint(),
            str_ends_with($path, '/notes') => ['id' => 5, 'status' => 'delivered'],
            str_ends_with($path, '/openapi.json') => null,
            default => 'list',
        };
        if ($object === null) {
            return $json(200, ['openapi' => '3.1.0']);
        }
        if ($object === 'list') {
            return $json(200, str_ends_with($path, '/items') ? ['data' => [], 'nextCursor' => null] : ['data' => [], 'next_cursor' => null]);
        }

        return $json(\in_array($r->getMethod(), ['POST', 'PUT'], true) && !str_ends_with($path, '/test') ? 201 : 200, ['data' => $object]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function task(): array
    {
        return ['id' => 2, 'project_id' => 1, 'title' => 'T', 'description' => null, 'status' => 'todo', 'is_review' => false, 'priority' => null, 'due_date' => null, 'start_date' => null, 'owner_user_id' => null, 'helpers' => [['member_id' => 4, 'user_id' => null]], 'partner_id' => null, 'work_package_id' => null, 'activity_id' => null, 'can_edit' => true, 'created_at' => null, 'updated_at' => null];
    }

    /**
     * @return array<string, mixed>
     */
    private static function endpoint(): array
    {
        return ['id' => 3, 'description' => 'CI', 'url' => 'https://hooks.example.com/...', 'events' => ['task.created'], 'project_ids' => null, 'enabled' => true, 'disabled_reason' => null, 'consecutive_failures' => 0, 'last_status' => null, 'last_delivery_at' => null, 'created_at' => '2026-10-24T09:00:00Z'];
    }

    public function testFiltersBecomeQueryParameters(): void
    {
        $m = new MockClient(MockClient::json(200, ['data' => [], 'next_cursor' => null]));
        $c = $m->client();
        $c->tasks->list(1, ['status' => 'stuck', 'limit' => 50]);
        $c->files->list(1, ['parent_id' => 9]);
        $c->projects->activities(1);
        self::assertSame(['status=stuck&limit=50', 'parent_id=9', ''], array_map(static fn(RequestInterface $r): string => $r->getUri()->getQuery(), $m->requests));
    }

    public function testTypedModelsKeepUnknownFields(): void
    {
        $m = new MockClient(MockClient::json(200, ['data' => self::task() + ['new_field' => 'x']]));
        $task = $m->client()->tasks->get(1, 2);
        self::assertSame('T', $task->title);
        self::assertSame(4, $task->helpers[0]->memberId);
        self::assertTrue($task->canEdit);
        self::assertSame('x', $task->toArray()['new_field']);
    }

    public function testMalformedAnswersAreReportedNotGuessed(): void
    {
        foreach ([['data' => ['id' => 'not-an-int']], ['id' => 2], [1, 2]] as $body) {
            $m = new MockClient(MockClient::json(200, $body));
            try {
                $m->client()->tasks->get(1, 2);
                self::fail('accepted');
            } catch (ImprojException $e) {
                self::assertSame('INVALID_RESPONSE', $e->getErrorCode());
            }
        }
        foreach ([['items' => []], ['data' => [1]], ['data' => [], 'next_cursor' => 5.5], [1]] as $body) {
            $m = new MockClient(MockClient::json(200, $body));
            try {
                $m->client()->tasks->list(1);
                self::fail('accepted');
            } catch (ImprojException $e) {
                self::assertSame('INVALID_RESPONSE', $e->getErrorCode());
            }
        }
        $m = new MockClient(MockClient::json(200, ['data' => 'x']));
        $this->expectException(ImprojException::class);
        $m->client()->agents->list();
    }

    public function testPagination(): void
    {
        $t = static fn(int $id): array => ['id' => $id] + self::task();
        $m = new MockClient(
            MockClient::json(200, ['data' => [$t(1), $t(2)], 'next_cursor' => 'c2']),
            MockClient::json(200, ['data' => [$t(3)], 'next_cursor' => null]),
        );
        $page = $m->client()->tasks->list(1, ['limit' => 2]);
        self::assertCount(2, $page);
        self::assertTrue($page->hasNextPage());
        self::assertSame([1, 2], array_map(static fn(Task $x): int => $x->id, iterator_to_array($page)));
        $ids = [];
        foreach ($page->autoPaging() as $task) {
            $ids[] = $task->id;
        }
        self::assertSame([1, 2, 3], $ids);
        self::assertSame('limit=2&cursor=c2', $m->requests[1]->getUri()->getQuery());

        $m = new MockClient(MockClient::json(200, ['data' => [$t(1)], 'next_cursor' => null]));
        $last = $m->client()->tasks->list(1, ['cursor' => 'start']);
        self::assertSame('cursor=start', $m->last()->getUri()->getQuery());
        $this->expectException(ImprojException::class);
        $last->nextPage();
    }

    public function testAllWalksEveryPageAndStopsOnARepeatedCursor(): void
    {
        $m = new MockClient(
            MockClient::json(200, ['data' => [], 'next_cursor' => 'same']),
            MockClient::json(200, ['data' => [], 'next_cursor' => 'same']),
        );
        try {
            iterator_to_array($m->client()->projects->all());
            self::fail('looped');
        } catch (ImprojException $e) {
            self::assertSame('PAGINATION_LOOP', $e->getErrorCode());
        }
        foreach (['tasks', 'milestones', 'members', 'files', 'calendarEvents', 'meetings'] as $name) {
            $m = new MockClient(MockClient::json(200, ['data' => [], 'next_cursor' => null]));
            $resource = $m->client()->{$name};
            self::assertSame([], iterator_to_array($resource->all(1)));
        }
        $m = new MockClient(MockClient::json(200, ['data' => [], 'next_cursor' => null]));
        self::assertSame([], iterator_to_array($m->client()->webhooks->all()));
    }

    public function testAgentItemsFollowTheNumericCursor(): void
    {
        // A server older than the 2026-10-24 contract sends only the numeric nextCursor.
        $m = new MockClient(MockClient::json(200, ['data' => [SyncTest::agentItem(9)], 'nextCursor' => 9]), MockClient::json(200, ['data' => [SyncTest::agentItem(8)], 'nextCursor' => null]));
        $items = iterator_to_array($m->client()->agents->items(4)->autoPaging(), false);
        self::assertSame([9, 8], array_map(static fn(\Kodvalley\Improj\Model\AgentItem $i): int => $i->id, $items));
        self::assertSame('cursor=9', $m->requests[1]->getUri()->getQuery());
    }

    public function testRefusesIdsThatCouldChangeThePath(): void
    {
        $c = (new MockClient(MockClient::json(200, [])))->client();
        foreach ([
            static fn() => $c->tasks->get('1/../../admin', 2),
            static fn() => $c->tasks->get(0, 2),
            static fn() => $c->files->list(1, ['parent_id' => -1]),
            static fn() => $c->projects->activities(1, ['work_package_id' => 0]),
            static fn() => $c->calendar->list('2026-1-1', '2026-01-31'),
        ] as $call) {
            try {
                $call();
                self::fail('accepted');
            } catch (ConfigurationException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testWebhookSecretNeverShowsInDumps(): void
    {
        $m = new MockClient(MockClient::json(201, ['data' => ['endpoint' => self::endpoint(), 'secret' => 'whsec_TEST_ONLY_not_a_real_secret']]));
        $created = $m->client()->webhooks->create(['url' => 'https://hooks.example.com/x', 'description' => 'CI', 'events' => ['task.created']]);
        self::assertSame('whsec_TEST_ONLY_not_a_real_secret', $created->secret);
        self::assertStringNotContainsString('not_a_real_secret', print_r($created, true));
    }
}
