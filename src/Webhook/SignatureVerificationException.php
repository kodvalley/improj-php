<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Webhook;

use Kodvalley\Improj\Exception\ImprojException;

/**
 * A webhook delivery failed verification. getReason() is one of the REASON_* constants.
 */
final class SignatureVerificationException extends ImprojException
{
    public const REASON_MISSING_HEADER = 'missing_header';
    public const REASON_MALFORMED_HEADER = 'malformed_header';
    public const REASON_TIMESTAMP_OUTSIDE_TOLERANCE = 'timestamp_outside_tolerance';
    public const REASON_NO_MATCHING_SIGNATURE = 'no_matching_signature';
    public const REASON_INVALID_PAYLOAD = 'invalid_payload';

    public function __construct(private readonly string $reason, string $message)
    {
        parent::__construct($message, 'WEBHOOK_SIGNATURE_INVALID');
    }

    public function getReason(): string
    {
        return $this->reason;
    }
}
