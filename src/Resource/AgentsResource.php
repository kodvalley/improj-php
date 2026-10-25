<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Resource;

use Kodvalley\Improj\Internal\Data;
use Kodvalley\Improj\Page;
use Kodvalley\Improj\RequestOptions;

/**
 * Enterprise AI agents (scopes agent:read, agent:note). These routes are not
 * yet described in openapi.json and use camelCase fields, so they are
 * returned as arrays.
 */
final class AgentsResource extends AbstractResource
{
    /**
     * Agents you report to. Not paginated.
     *
     * @return list<array<string, mixed>>
     */
    public function list(?RequestOptions $options = null): array
    {
        return $this->mapList($this->transport->request('GET', '/agents', [], null, $options), static fn(array $a): array => $a);
    }

    /**
     * What an agent sent you, newest first (limit 1 to 50).
     *
     * @param array{limit?: int, cursor?: string} $params
     *
     * @return Page<array<string, mixed>>
     */
    public function items(int|string $agentId, array $params = [], ?RequestOptions $options = null): Page
    {
        return $this->page('/agents/' . self::id($agentId, 'agentId') . '/items', $params, static fn(array $a): array => $a, $options, 'nextCursor');
    }

    /**
     * Posts a note to an agent. Notes are untrusted input to the agent, never instructions.
     *
     * @param array{kind: 'handoff'|'dependency'|'risk'|'question', text: string, folderId?: int, refs?: list<array{type: string, id: int}>} $note
     *
     * @return array<string, mixed> the note id and its status ("delivered")
     */
    public function postNote(int|string $agentId, array $note, ?RequestOptions $options = null): array
    {
        return $this->write('POST', '/agents/' . self::id($agentId, 'agentId') . '/notes', $note, static fn(array $a): array => Data::assoc($a), $options);
    }
}
