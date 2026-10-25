<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Webhook;

use InvalidArgumentException;
use JsonException;
use Kodvalley\Improj\Model\WebhookEvent;
use UnexpectedValueException;

/**
 * Verifies IMPROJ webhook deliveries.
 *
 *     IMPROJ-Signature: t=<unix seconds>,v1=<hex HMAC-SHA256(secret, "<t>." . raw body)>
 *
 * Pass the raw request body exactly as received (php://input), before any
 * JSON decoding: a decoded and re-encoded body has different bytes.
 *
 *     $verifier = new WebhookVerifier(getenv('IMPROJ_WEBHOOK_SECRET'));
 *     $event = $verifier->constructEvent(file_get_contents('php://input'), $_SERVER['HTTP_IMPROJ_SIGNATURE'] ?? null);
 */
final class WebhookVerifier
{
    public const SIGNATURE_HEADER = 'IMPROJ-Signature';
    public const EVENT_HEADER = 'IMPROJ-Event';
    public const DELIVERY_HEADER = 'IMPROJ-Delivery';
    public const WEBHOOK_ID_HEADER = 'IMPROJ-Webhook-Id';
    public const API_VERSION_HEADER = 'IMPROJ-API-Version';
    public const DEFAULT_TOLERANCE = 300;

    private const MAX_HEADER_LENGTH = 4096;
    private const MAX_SIGNATURES = 10;

    /**
     * @param string $secret    the endpoint's signing secret (whsec_...)
     * @param int    $tolerance maximum age and clock skew of the timestamp, in seconds
     */
    public function __construct(
        #[\SensitiveParameter]
        private readonly string $secret,
        private readonly int $tolerance = self::DEFAULT_TOLERANCE,
    ) {
        if (\strlen($secret) < 8) {
            throw new InvalidArgumentException('Pass the endpoint signing secret (whsec_...).');
        }
        if ($tolerance <= 0) {
            throw new InvalidArgumentException('The tolerance must be a positive number of seconds.');
        }
    }

    /**
     * Throws SignatureVerificationException (with a reason) unless the delivery is authentic and fresh.
     *
     * @param int|null $now the current Unix time (for tests)
     */
    public function verify(string $payload, ?string $signatureHeader, ?int $now = null): void
    {
        [$timestamp, $signatures] = self::parseHeader($signatureHeader);
        $now ??= time();
        if (abs($now - $timestamp) > $this->tolerance) {
            throw new SignatureVerificationException(
                SignatureVerificationException::REASON_TIMESTAMP_OUTSIDE_TOLERANCE,
                sprintf('The signature timestamp is more than %d seconds away from the current time.', $this->tolerance),
            );
        }
        $expected = $this->computeSignature($timestamp, $payload);
        $match = false;
        foreach ($signatures as $signature) {
            // Constant-time comparison of every candidate; no early exit.
            if (hash_equals($expected, $signature)) {
                $match = true;
            }
        }
        if (!$match) {
            throw new SignatureVerificationException(SignatureVerificationException::REASON_NO_MATCHING_SIGNATURE, 'No signature matches the payload and secret.');
        }
    }

    /** True when the delivery is authentic and fresh. */
    public function isValid(string $payload, ?string $signatureHeader, ?int $now = null): bool
    {
        try {
            $this->verify($payload, $signatureHeader, $now);

            return true;
        } catch (SignatureVerificationException) {
            return false;
        }
    }

    /** Verifies the delivery, then decodes it. Use the event's ids to fetch current data through the API. */
    public function constructEvent(string $payload, ?string $signatureHeader, ?int $now = null): WebhookEvent
    {
        $this->verify($payload, $signatureHeader, $now);
        try {
            $data = json_decode($payload, true, 64, \JSON_THROW_ON_ERROR);
            if (!\is_array($data) || array_is_list($data)) {
                throw new UnexpectedValueException('not an object');
            }
            /** @var array<string, mixed> $data */
            return WebhookEvent::fromArray($data);
        } catch (JsonException|UnexpectedValueException) {
            throw new SignatureVerificationException(SignatureVerificationException::REASON_INVALID_PAYLOAD, 'The verified body is not an IMPROJ webhook event.');
        }
    }

    /** HMAC-SHA256 of "<t>." . payload, as lowercase hex. */
    public function computeSignature(int $timestamp, string $payload): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $payload, $this->secret);
    }

    /** An IMPROJ-Signature header value for a payload (to test your receiver). */
    public function signatureHeader(string $payload, ?int $timestamp = null): string
    {
        $timestamp ??= time();

        return sprintf('t=%d,v1=%s', $timestamp, $this->computeSignature($timestamp, $payload));
    }

    /**
     * @return array{0: int, 1: list<string>}
     */
    public static function parseHeader(?string $header): array
    {
        if ($header === null || $header === '') {
            throw new SignatureVerificationException(SignatureVerificationException::REASON_MISSING_HEADER, 'The IMPROJ-Signature header is missing.');
        }
        if (\strlen($header) > self::MAX_HEADER_LENGTH) {
            throw new SignatureVerificationException(SignatureVerificationException::REASON_MALFORMED_HEADER, 'The IMPROJ-Signature header is malformed.');
        }
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            $pos = strpos($part, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($part, 0, $pos));
            $value = trim(substr($part, $pos + 1));
            if ($key === 't' && preg_match('/^\d{1,12}$/', $value) === 1) {
                $timestamp = (int) $value;
            } elseif ($key === 'v1' && preg_match('/^[0-9a-fA-F]{64}$/', $value) === 1 && \count($signatures) < self::MAX_SIGNATURES) {
                $signatures[] = strtolower($value);
            }
        }
        if ($timestamp === null || $signatures === []) {
            throw new SignatureVerificationException(SignatureVerificationException::REASON_MALFORMED_HEADER, 'The IMPROJ-Signature header has no timestamp or no v1 signature.');
        }

        return [$timestamp, $signatures];
    }

    /** var_dump() and print_r() never show the secret. */
    public function __debugInfo(): array
    {
        return ['secret' => 'whsec_***', 'tolerance' => $this->tolerance];
    }
}
