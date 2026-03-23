<?php

declare(strict_types=1);

namespace Psr\Http\Factory;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

interface RequestFactoryInterface
{
    public function createRequest(string $method, $uri): RequestInterface;
}
