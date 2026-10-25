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
     * @param array<string, string> $headers        Extra headers. Authorization, Cookie, X-Request-Id,
     *                                              IMPROJ-API-Version and Idempotency-Key are refused here.
     * @param string|null           $requestId      Your own id for this request, sent as X-Request-Id
     *                                              (8 to 64 characters of A-Z a-z 0-9 . _ : -). The API
     *                                              echoes it in X-Request-Id and in getRequestId().
     * @param string|false|null     $apiVersion     IMPROJ-API-Version for this request: null uses the
     *                                              client's pin, false sends none.
     */
    public function __construct(
        public readonly ?string $idempotencyKey = null,
        public readonly ?int $maxRetries = null,
        public readonly array $headers = [],
        public readonly ?string $requestId = null,
        public readonly string|false|null $apiVersion = null,
    ) {}
}
