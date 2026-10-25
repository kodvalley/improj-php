<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Exception;

/**
 * 422 Unprocessable Content, for example IDEMPOTENCY_KEY_REUSED.
 */
final class UnprocessableException extends ImprojException {}
