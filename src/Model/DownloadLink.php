<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Model;

use Kodvalley\Improj\Internal\Data;

/**
 * A signed download link, valid for 5 minutes.
 */
final class DownloadLink extends Model
{
    public readonly string $url;

    public readonly string $expiresAt;

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(array $data)
    {
        parent::__construct($data);
        $this->url = Data::string($data, 'url');
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
