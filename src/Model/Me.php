<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Model;

use Kodvalley\Improj\Internal\Data;

/**
 * The token's owner and the token itself (GET /me).
 */
final class Me extends Model
{
    public readonly int $id;

    public readonly ?string $name;

    public readonly ?string $email;

    public readonly ?string $avatarUrl;

    public readonly ?string $timeZone;

    public readonly MeToken $token;

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(array $data)
    {
        parent::__construct($data);
        $this->id = Data::int($data, 'id');
        $this->name = Data::stringOrNull($data, 'name');
        $this->email = Data::stringOrNull($data, 'email');
        $this->avatarUrl = Data::stringOrNull($data, 'avatar_url');
        $this->timeZone = Data::stringOrNull($data, 'time_zone');
        $this->token = MeToken::fromArray(Data::object($data, 'token'));
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
