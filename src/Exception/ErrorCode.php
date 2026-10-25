<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Exception;

/**
 * Error codes the API documents (openapi.json, components.responses). Compare
 * them with ImprojException::getErrorCode(). The API may add codes, so treat
 * an unknown code by its HTTP status.
 */
final class ErrorCode
{
    public const INVALID_INPUT = 'INVALID_INPUT';
    public const API_CURSOR_INVALID = 'API_CURSOR_INVALID';
    public const API_VERSION_UNSUPPORTED = 'API_VERSION_UNSUPPORTED';
    public const FILE_TYPE_NOT_ALLOWED = 'FILE_TYPE_NOT_ALLOWED';
    public const API_UPLOAD_SIZE_MISMATCH = 'API_UPLOAD_SIZE_MISMATCH';
    public const WEBHOOK_URL_INVALID = 'WEBHOOK_URL_INVALID';
    public const API_TOKEN_MISSING = 'API_TOKEN_MISSING';
    public const API_TOKEN_INVALID = 'API_TOKEN_INVALID';
    public const API_SCOPE_MISSING = 'API_SCOPE_MISSING';
    public const API_IP_NOT_ALLOWED = 'API_IP_NOT_ALLOWED';
    public const API_ORIGIN_NOT_ALLOWED = 'API_ORIGIN_NOT_ALLOWED';
    public const API_NOT_IN_PLAN = 'API_NOT_IN_PLAN';
    public const API_DISABLED = 'API_DISABLED';
    public const STORAGE_QUOTA_FULL = 'STORAGE_QUOTA_FULL';
    public const VIEWER_READ_ONLY = 'VIEWER_READ_ONLY';
    public const PROJECT_ARCHIVED_READ_ONLY = 'PROJECT_ARCHIVED_READ_ONLY';
    public const NOT_ALLOWED = 'NOT_ALLOWED';
    public const AGENT_NOT_ENTITLED = 'AGENT_NOT_ENTITLED';
    public const API_WEBHOOK_LIMIT_REACHED = 'API_WEBHOOK_LIMIT_REACHED';
    public const NOT_FOUND = 'NOT_FOUND';
    public const API_UPLOAD_EXPIRED = 'API_UPLOAD_EXPIRED';
    public const API_METHOD_NOT_ALLOWED = 'API_METHOD_NOT_ALLOWED';
    public const IDEMPOTENCY_KEY_IN_USE = 'IDEMPOTENCY_KEY_IN_USE';
    public const API_PAYLOAD_TOO_LARGE = 'API_PAYLOAD_TOO_LARGE';
    public const FILE_TOO_LARGE_FOR_PLAN = 'FILE_TOO_LARGE_FOR_PLAN';
    public const API_UNSUPPORTED_MEDIA_TYPE = 'API_UNSUPPORTED_MEDIA_TYPE';
    public const IDEMPOTENCY_KEY_REUSED = 'IDEMPOTENCY_KEY_REUSED';
    public const RATE_LIMITED = 'RATE_LIMITED';
    public const SOMETHING_WENT_WRONG = 'SOMETHING_WENT_WRONG';
    public const DB_UNAVAILABLE = 'DB_UNAVAILABLE';
    public const SERVER_BUSY = 'SERVER_BUSY';

    /** The SDK's own codes, for failures detected before or without an answer. */
    public const CONFIGURATION = 'CONFIGURATION';
    public const CONNECTION_ERROR = 'CONNECTION_ERROR';
    public const INVALID_RESPONSE = 'INVALID_RESPONSE';
    public const UNEXPECTED_REDIRECT = 'UNEXPECTED_REDIRECT';
    public const NO_MORE_PAGES = 'NO_MORE_PAGES';
    public const PAGINATION_LOOP = 'PAGINATION_LOOP';

    /** Every code the API documents. */
    public const API_CODES = [
        self::INVALID_INPUT,
        self::API_CURSOR_INVALID,
        self::API_VERSION_UNSUPPORTED,
        self::FILE_TYPE_NOT_ALLOWED,
        self::API_UPLOAD_SIZE_MISMATCH,
        self::WEBHOOK_URL_INVALID,
        self::API_TOKEN_MISSING,
        self::API_TOKEN_INVALID,
        self::API_SCOPE_MISSING,
        self::API_IP_NOT_ALLOWED,
        self::API_ORIGIN_NOT_ALLOWED,
        self::API_NOT_IN_PLAN,
        self::API_DISABLED,
        self::STORAGE_QUOTA_FULL,
        self::VIEWER_READ_ONLY,
        self::PROJECT_ARCHIVED_READ_ONLY,
        self::NOT_ALLOWED,
        self::AGENT_NOT_ENTITLED,
        self::API_WEBHOOK_LIMIT_REACHED,
        self::NOT_FOUND,
        self::API_UPLOAD_EXPIRED,
        self::API_METHOD_NOT_ALLOWED,
        self::IDEMPOTENCY_KEY_IN_USE,
        self::API_PAYLOAD_TOO_LARGE,
        self::FILE_TOO_LARGE_FOR_PLAN,
        self::API_UNSUPPORTED_MEDIA_TYPE,
        self::IDEMPOTENCY_KEY_REUSED,
        self::RATE_LIMITED,
        self::SOMETHING_WENT_WRONG,
        self::DB_UNAVAILABLE,
        self::SERVER_BUSY,
    ];

    /** @codeCoverageIgnore Constants only. */
    private function __construct() {}
}
