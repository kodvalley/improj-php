<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Tests;

use Kodvalley\Improj\Model\CalendarItem;
use Kodvalley\Improj\Model\Member;
use Kodvalley\Improj\Tests\Support\MockClient;
use PHPUnit\Framework\TestCase;

/** Every list answer parses into its typed model, through the public API. */
final class ModelsTest extends TestCase
{
    public function testListModelsParse(): void
    {
        $m = new MockClient(
            MockClient::json(200, ['data' => [['id' => 1, 'project_id' => 42, 'number' => 'WP1', 'title' => 'Management', 'description' => null, 'start_date' => '2026-01-01', 'end_date' => null, 'status' => 'active', 'color' => '#336699']], 'next_cursor' => null]),
            MockClient::json(200, ['data' => [['id' => 7, 'project_id' => 42, 'work_package_id' => 1, 'number' => 'A1.1', 'title' => 'Kick-off', 'description' => null, 'type' => 'meeting', 'start_date' => null, 'end_date' => null, 'status' => null, 'location' => 'Vilnius', 'is_online' => false]], 'next_cursor' => null]),
            MockClient::json(200, ['data' => [
                ['id' => 'm_3', 'user_id' => 9, 'name' => 'Ada', 'email' => null, 'role' => 'member', 'partner' => ['id' => 2, 'name' => 'Partner A'], 'is_project_owner' => false, 'is_coordinator' => true],
                ['id' => 'm_4', 'user_id' => null, 'name' => 'Guest', 'email' => null, 'role' => null, 'partner' => null, 'is_project_owner' => false, 'is_coordinator' => false],
            ], 'next_cursor' => null]),
            MockClient::json(200, ['data' => [
                ['id' => 'ev_1', 'source' => 'project', 'title' => 'Review', 'start_date' => '2026-11-02', 'end_date' => null, 'start_time' => '09:30', 'end_time' => '10:30', 'all_day' => false, 'time_zone' => 'Europe/Vilnius', 'location' => null, 'meeting_url' => null, 'status' => 'scheduled', 'project' => ['id' => 42, 'name' => 'P']],
                ['id' => 'ev_2', 'source' => 'personal', 'title' => null, 'start_date' => null, 'end_date' => null, 'start_time' => null, 'end_time' => null, 'all_day' => true, 'time_zone' => null, 'location' => null, 'meeting_url' => null, 'status' => null, 'project' => null],
            ]]),
            MockClient::json(200, ['data' => [['id' => 5, 'event_id' => 6, 'event' => 'task.created', 'status' => 'delivered', 'attempts' => 1, 'response_code' => 204, 'error' => null, 'next_attempt_at' => null, 'created_at' => '2026-10-24T09:00:00Z', 'delivered_at' => '2026-10-24T09:00:01Z']], 'next_cursor' => null]),
            MockClient::json(200, ['data' => [['id' => 3, 'description' => 'CI', 'url' => 'https://hooks.example.com/...', 'events' => ['task.created'], 'project_ids' => [42], 'enabled' => false, 'disabled_reason' => 'failures', 'consecutive_failures' => 15, 'last_status' => 'failed', 'last_delivery_at' => null, 'created_at' => '2026-10-24T09:00:00Z']], 'next_cursor' => null]),
        );
        $c = $m->client();

        $wp = $c->projects->workPackages(42)->data[0];
        self::assertSame('WP1', $wp->number);
        self::assertSame('#336699', $wp->color);

        $activity = $c->projects->activities(42)->data[0];
        self::assertSame(1, $activity->workPackageId);
        self::assertFalse($activity->isOnline);

        [$ada, $guest] = $c->members->list(42)->data;
        self::assertInstanceOf(Member::class, $ada);
        self::assertSame('Partner A', $ada->partner?->name);
        self::assertTrue($ada->isCoordinator);
        self::assertNull($guest->partner);

        $items = $c->calendar->list('2026-11-01', '2026-11-30');
        self::assertContainsOnlyInstancesOf(CalendarItem::class, $items);
        self::assertSame(42, $items[0]->project?->id);
        self::assertSame('Europe/Vilnius', $items[0]->timeZone);
        self::assertNull($items[1]->project);
        self::assertSame('from=2026-11-01&to=2026-11-30', $m->requests[3]->getUri()->getQuery());

        $delivery = $c->webhooks->deliveries(3)->data[0];
        self::assertSame(204, $delivery->responseCode);
        self::assertSame('task.created', $delivery->event);

        $endpoint = $c->webhooks->list()->data[0];
        self::assertSame([42], $endpoint->projectIds);
        self::assertSame('failures', $endpoint->disabledReason);
    }
}
