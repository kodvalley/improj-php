<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Model;

use Kodvalley\Improj\Internal\Data;

/**
 * A webhook endpoint. url is shown masked.
 */
final class WebhookEndpoint extends Model
{
    public readonly int $id;

    public readonly string $description;

    public readonly string $url;

    /** @var list<string> */
    public readonly array $events;

    /** @var list<int>|null */
    public readonly ?array $projectIds;

    public readonly bool $enabled;

    public readonly ?string $disabledReason;

    public readonly int $consecutiveFailures;

    public readonly ?string $lastStatus;

    public readonly ?string $lastDeliveryAt;

    public readonly string $createdAt;

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(array $data)
    {
        parent::__construct($data);
        $this->id = Data::int($data, 'id');
        $this->description = Data::string($data, 'description');
        $this->url = Data::string($data, 'url');
        $this->events = Data::strings($data, 'events');
        $this->projectIds = Data::intsOrNull($data, 'project_ids');
        $this->enabled = Data::bool($data, 'enabled');
        $this->disabledReason = Data::stringOrNull($data, 'disabled_reason');
        $this->consecutiveFailures = Data::int($data, 'consecutive_failures');
        $this->lastStatus = Data::stringOrNull($data, 'last_status');
        $this->lastDeliveryAt = Data::stringOrNull($data, 'last_delivery_at');
        $this->createdAt = Data::string($data, 'created_at');
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
