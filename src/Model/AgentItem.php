<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Model;

use Kodvalley\Improj\Internal\Data;

/**
 * Something an agent sent you. Always AI generated: show $notice next to it.
 */
final class AgentItem extends Model
{
    public readonly int $id;

    public readonly string $kind;

    public readonly string $severity;

    public readonly string $title;

    public readonly string $body;

    public readonly ?ProjectRef $project;

    /** @var list<AgentCitation> */
    public readonly array $citations;

    public readonly bool $aiGenerated;

    public readonly string $notice;

    public readonly string $status;

    public readonly bool $read;

    public readonly ?string $createdAt;

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(array $data)
    {
        parent::__construct($data);
        $this->id = Data::int($data, 'id');
        $this->kind = Data::string($data, 'kind');
        $this->severity = Data::string($data, 'severity');
        $this->title = Data::string($data, 'title');
        $this->body = Data::string($data, 'body');
        $this->project = ($o = Data::objectOrNull($data, 'project')) === null ? null : ProjectRef::fromArray($o);
        $this->citations = array_map(static fn(array $x): AgentCitation => AgentCitation::fromArray($x), Data::objects($data, 'citations'));
        $this->aiGenerated = Data::bool($data, 'aiGenerated');
        $this->notice = Data::string($data, 'notice');
        $this->status = Data::string($data, 'status');
        $this->read = Data::bool($data, 'read');
        $this->createdAt = Data::stringOrNull($data, 'createdAt');
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws \UnexpectedValueException when a documented field is missing or has the wrong type
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }
}
