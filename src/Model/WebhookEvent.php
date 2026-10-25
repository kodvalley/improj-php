<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Model;

use Kodvalley\Improj\Internal\Data;

/**
 * The body of a webhook delivery (thin: ids only). Fetch the object through the API.
 */
final class WebhookEvent extends Model
{
    public readonly string $id;

    public readonly string $type;

    public readonly string $apiVersion;

    public readonly string $createdAt;

    public readonly int $deliveryId;

    public readonly WebhookEventData $data;

    public readonly ?int $actorUserId;

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(array $data)
    {
        parent::__construct($data);
        $this->id = Data::string($data, 'id');
        $this->type = Data::string($data, 'type');
        $this->apiVersion = Data::string($data, 'api_version');
        $this->createdAt = Data::string($data, 'created_at');
        $this->deliveryId = Data::int($data, 'delivery_id');
        $this->data = WebhookEventData::fromArray(Data::object($data, 'data'));
        $this->actorUserId = Data::intOrNull($data, 'actor_user_id');
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
