<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Exception;

/**
 * No answer arrived (DNS, TLS, connection reset, timeout of the HTTP client).
 */
final class ConnectionException extends ImprojException {}
