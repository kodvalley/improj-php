<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Exception;

/**
 * 413 Content Too Large: the body is over the limit (API_PAYLOAD_TOO_LARGE) or the file exceeds the plan (FILE_TOO_LARGE_FOR_PLAN).
 */
final class PayloadTooLargeException extends ImprojException {}
