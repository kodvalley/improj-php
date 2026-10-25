<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Model;

use Kodvalley\Improj\Internal\Data;

/**
 * One delivery attempt record of an endpoint.
 */
final class WebhookDelivery extends Model
{
    public readonly int $id;

    public readonly int $eventId;

    public readonly string $event;

    public readonly string $status;

    public readonly int $attempts;

    public readonly ?int $responseCode;

    public readonly ?string $error;

    public readonly ?string $nextAttemptAt;

    public readonly string $createdAt;

    public readonly ?string $deliveredAt;

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(array $data)
    {
        parent::__construct($data);
        $this->id = Data::int($data, 'id');
        $this->eventId = Data::int($data, 'event_id');
        $this->event = Data::string($data, 'event');
        $this->status = Data::string($data, 'status');
        $this->attempts = Data::int($data, 'attempts');
        $this->responseCode = Data::intOrNull($data, 'response_code');
        $this->error = Data::stringOrNull($data, 'error');
        $this->nextAttemptAt = Data::stringOrNull($data, 'next_attempt_at');
        $this->createdAt = Data::string($data, 'created_at');
        $this->deliveredAt = Data::stringOrNull($data, 'delivered_at');
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
