<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Model;

use Kodvalley\Improj\Internal\Data;

/**
 * A person on a project. email follows the person's privacy setting.
 */
final class Member extends Model
{
    public readonly string $id;

    public readonly ?int $userId;

    public readonly ?string $name;

    public readonly ?string $email;

    public readonly ?string $role;

    public readonly ?MemberPartner $partner;

    public readonly bool $isProjectOwner;

    public readonly bool $isCoordinator;

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(array $data)
    {
        parent::__construct($data);
        $this->id = Data::string($data, 'id');
        $this->userId = Data::intOrNull($data, 'user_id');
        $this->name = Data::stringOrNull($data, 'name');
        $this->email = Data::stringOrNull($data, 'email');
        $this->role = Data::stringOrNull($data, 'role');
        $this->partner = ($o = Data::objectOrNull($data, 'partner')) === null ? null : MemberPartner::fromArray($o);
        $this->isProjectOwner = Data::bool($data, 'is_project_owner');
        $this->isCoordinator = Data::bool($data, 'is_coordinator');
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
