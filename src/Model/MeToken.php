<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Model;

use Kodvalley\Improj\Internal\Data;

/**
 * The token used for the request.
 */
final class MeToken extends Model
{
    public readonly int $id;

    public readonly string $kind;

    /** @var list<string> */
    public readonly array $scopes;

    public readonly ?int $organizationId;

    public readonly ?string $expiresAt;

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(array $data)
    {
        parent::__construct($data);
        $this->id = Data::int($data, 'id');
        $this->kind = Data::string($data, 'kind');
        $this->scopes = Data::strings($data, 'scopes');
        $this->organizationId = Data::intOrNull($data, 'organization_id');
        $this->expiresAt = Data::stringOrNull($data, 'expires_at');
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
