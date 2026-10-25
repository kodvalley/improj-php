<?php

declare(strict_types=1);

namespace Kodvalley\Improj;

/**
 * Per-request options; every resource method accepts them as its last argument.
 */
final class RequestOptions
{
    /**
     * @param string|null           $idempotencyKey Idempotency-Key for a POST (1 to 255 visible ASCII
     *                                              characters). By default the SDK sends a random UUID,
     *                                              so a retried POST is never applied twice. Pass your
     *                                              own to make a POST safe to repeat across processes.
     * @param int|null              $maxRetries     Overrides the client's retry count for this request.
     * @param array<string, string> $headers        Extra headers. Authorization and Cookie are refused.
     */
    public function __construct(
        public readonly ?string $idempotencyKey = null,
        public readonly ?int $maxRetries = null,
        public readonly array $headers = [],
    ) {}
}
