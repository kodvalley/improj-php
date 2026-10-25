<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Model;

use Kodvalley\Improj\Internal\Data;

/**
 * Step 1 of an upload: where to send the bytes.
 */
final class Upload extends Model
{
    public readonly string $id;

    public readonly string $uploadUrl;

    public readonly string $method;

    public readonly int $maxBytes;

    public readonly string $expiresAt;

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(array $data)
    {
        parent::__construct($data);
        $this->id = Data::string($data, 'id');
        $this->uploadUrl = Data::string($data, 'upload_url');
        $this->method = Data::string($data, 'method');
        $this->maxBytes = Data::int($data, 'max_bytes');
        $this->expiresAt = Data::string($data, 'expires_at');
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
