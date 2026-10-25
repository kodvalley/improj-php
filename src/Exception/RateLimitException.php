<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Exception;

/**
 * 429 Too Many Requests; getRetryAfter() says how long to wait.
 */
final class RateLimitException extends ImprojException {}
