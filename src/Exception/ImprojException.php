<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Exception;

use Kodvalley\Improj\Internal\Redactor;
use RuntimeException;
use Throwable;

/**
 * Base class of every exception the SDK throws.
 *
 * - getErrorCode(): the API's stable error code (for example NOT_FOUND), or an
 *   SDK code (CONNECTION_ERROR, INVALID_RESPONSE, CONFIGURATION...).
 * - getStatus(): the HTTP status, or 0 when no answer arrived.
 * - getRequestId(): the API's X-Request-Id; quote it when you contact support.
 *
 * getCode() returns the HTTP status as well. Messages are redacted: a token or
 * webhook secret never appears in them.
 */
class ImprojException extends RuntimeException
{
    /**
     * @param list<array{path: string, message: string}> $errors
     */
    public function __construct(
        string $message,
        private readonly string $errorCode,
        private readonly int $status = 0,
        private readonly ?string $requestId = null,
        private readonly array $errors = [],
        private readonly ?int $retryAfter = null,
        private readonly ?string $type = null,
        private readonly ?string $detail = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct(Redactor::redact($message), $status, $previous);
    }

    /** The stable error code, for example NOT_FOUND or INVALID_INPUT. */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /** The HTTP status, or 0 when no answer arrived. */
    public function getStatus(): int
    {
        return $this->status;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    /**
     * Field problems of a 400 INVALID_INPUT answer.
     *
     * @return list<array{path: string, message: string}>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /** Seconds to wait before retrying (429, 503). */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }

    /** The problem type URI. */
    public function getType(): ?string
    {
        return $this->type;
    }

    /** The human-readable detail of the problem (redacted). */
    public function getDetail(): ?string
    {
        return $this->detail === null ? null : Redactor::redact($this->detail);
    }

    /**
     * A plain array for structured logs (no headers, no credentials).
     *
     * @return array{error_code: string, status: int, request_id: ?string, message: string, errors: list<array{path: string, message: string}>}
     */
    public function toArray(): array
    {
        return [
            'error_code' => $this->errorCode,
            'status' => $this->status,
            'request_id' => $this->requestId,
            'message' => $this->getMessage(),
            'errors' => $this->errors,
        ];
    }
}
