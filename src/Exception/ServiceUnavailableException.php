<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Exception;

/**
 * 503 Service Unavailable (DB_UNAVAILABLE, SERVER_BUSY). Safe requests are retried before this is thrown.
 */
final class ServiceUnavailableException extends ServerException {}
