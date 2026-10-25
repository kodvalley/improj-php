<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Exception;

/**
 * 401 Unauthorized: the token is missing, invalid, expired or revoked.
 */
final class AuthenticationException extends ImprojException {}
