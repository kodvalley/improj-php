<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Exception;

/**
 * 5xx: a server-side failure. Safe requests are retried before this is thrown.
 */
class ServerException extends ImprojException {}
