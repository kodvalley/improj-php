<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Model;

use Kodvalley\Improj\Internal\Data;

/**
 * What a webhook event is about. id is a string for members, an integer otherwise.
 */
final class WebhookEventData extends Model
{
    public readonly string $object;

    public readonly int|string|null $id;

    public readonly ?int $projectId;

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(array $data)
    {
        parent::__construct($data);
        $this->object = Data::string($data, 'object');
        $this->id = self::id($data);
        $this->projectId = Data::intOrNull($data, 'project_id');
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

    /**
     * @param array<string, mixed> $data
     */
    private static function id(array $data): int|string|null
    {
        /** @var mixed $v */
        $v = $data['id'] ?? null;

        return \is_int($v) || \is_string($v) ? $v : null;
    }
}
