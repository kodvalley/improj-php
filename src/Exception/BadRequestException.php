<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Exception;

/**
 * 400 Bad Request: invalid input (see getErrors()), an unsupported API version, an invalid cursor...
 */
final class BadRequestException extends ImprojException {}
