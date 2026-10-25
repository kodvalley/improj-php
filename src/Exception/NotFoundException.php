<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Exception;

/**
 * 404 Not Found: missing, or not visible to the token's owner (indistinguishable on purpose).
 */
final class NotFoundException extends ImprojException {}
