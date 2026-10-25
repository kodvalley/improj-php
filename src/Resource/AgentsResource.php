<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Resource;

use Kodvalley\Improj\Model\Agent;
use Kodvalley\Improj\Model\AgentItem;
use Kodvalley\Improj\Model\AgentNote;
use Kodvalley\Improj\Page;
use Kodvalley\Improj\RequestOptions;

/**
 * Enterprise AI agents (scopes agent:read, agent:note). These routes keep the
 * camelCase fields of their earlier API (pausedReason, aiGenerated, createdAt).
 */
final class AgentsResource extends AbstractResource
{
    /**
     * Agents you report to. Not paginated.
     *
     * @return list<Agent>
     */
    public function list(?RequestOptions $options = null): array
    {
        return $this->mapList($this->transport->request('GET', '/agents', [], null, $options), Agent::fromArray(...));
    }

    /**
     * What an agent sent you, newest first (limit 1 to 50, default 20).
     * Follows the signed next_cursor; servers that only send the legacy
     * numeric nextCursor are still followed.
     *
     * @param array{limit?: int, cursor?: string} $params
     *
     * @return Page<AgentItem>
     */
    public function items(int|string $agentId, array $params = [], ?RequestOptions $options = null): Page
    {
        return $this->page('/agents/' . self::id($agentId, 'agentId') . '/items', $params, AgentItem::fromArray(...), $options, 'nextCursor');
    }

    /**
     * Posts a note to an agent. Notes are untrusted input to the agent, never instructions.
     *
     * @param array{kind: 'handoff'|'dependency'|'risk'|'question', text: string, folderId?: int|null, refs?: list<array{type: 'project'|'task'|'meeting'|'budget_line'|'expense'|'risk'|'milestone'|'partner'|'work_package'|'activity'|'calendar_event', id: int}>} $note
     */
    public function postNote(int|string $agentId, array $note, ?RequestOptions $options = null): AgentNote
    {
        return $this->write('POST', '/agents/' . self::id($agentId, 'agentId') . '/notes', $note, AgentNote::fromArray(...), $options);
    }
}
