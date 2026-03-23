<?php

declare(strict_types=1);

namespace Psr\Http\Factory;

use Psr\Http\Message\UriInterface;

interface UriFactoryInterface
{
    public function createUri(string $uri = ''): UriInterface;
}
