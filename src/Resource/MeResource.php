<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Resource;

use Kodvalley\Improj\Model\Me;
use Kodvalley\Improj\RequestOptions;

/**
 * GET /me (scope user:read).
 */
final class MeResource extends AbstractResource
{
    /** The token's owner and the token itself. */
    public function get(?RequestOptions $options = null): Me
    {
        return $this->getOne('/me', Me::fromArray(...), $options);
    }
}
