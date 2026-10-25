<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Exception;

/**
 * 409 Conflict, for example IDEMPOTENCY_KEY_IN_USE.
 */
final class ConflictException extends ImprojException {}
