<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Model;

use Kodvalley\Improj\Internal\Data;

/**
 * A Helper of a task (a project member).
 */
final class TaskHelper extends Model
{
    public readonly int $memberId;

    public readonly ?int $userId;

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(array $data)
    {
        parent::__construct($data);
        $this->memberId = Data::int($data, 'member_id');
        $this->userId = Data::intOrNull($data, 'user_id');
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
