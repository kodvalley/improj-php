<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Model;

use Kodvalley\Improj\Internal\Data;

/**
 * The result of a test ping.
 */
final class WebhookTestResult extends Model
{
    public readonly string $status;

    public readonly ?int $responseCode;

    public readonly ?string $error;

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(array $data)
    {
        parent::__construct($data);
        $this->status = Data::string($data, 'status');
        $this->responseCode = Data::intOrNull($data, 'response_code');
        $this->error = Data::stringOrNull($data, 'error');
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
