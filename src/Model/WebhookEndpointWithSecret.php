<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Model;

use Kodvalley\Improj\Internal\Data;

/**
 * A new webhook endpoint and its signing secret, which the API shows only once.
 */
final class WebhookEndpointWithSecret extends Model
{
    public readonly WebhookEndpoint $endpoint;

    /** whsec_... Store it in your secret manager; it is not shown again. */
    public readonly string $secret;

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(array $data)
    {
        parent::__construct($data);
        $this->endpoint = WebhookEndpoint::fromArray(Data::object($data, 'endpoint'));
        $this->secret = Data::string($data, 'secret');
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /** var_dump() and print_r() never show the secret. */
    public function __debugInfo(): array
    {
        return ['endpoint' => $this->endpoint, 'secret' => 'whsec_***'];
    }
}
