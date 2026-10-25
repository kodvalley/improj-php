<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Exception;

/**
 * 403 Forbidden: missing scope, IP or origin not allowed, plan, or the app refuses the action.
 */
final class PermissionException extends ImprojException {}
