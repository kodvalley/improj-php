<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Tests\Support;

use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

/** What a PSR-18 client throws for a request it cannot send. */
final class BrokenRequest extends RuntimeException implements RequestExceptionInterface
{
    public function __construct(private readonly RequestInterface $request)
    {
        parent::__construct('invalid request');
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
