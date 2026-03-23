<?php

declare(strict_types=1);

namespace Psr\Http\Client;

use Psr\Http\Message\RequestInterface;

interface RequestExceptionInterface extends ClientExceptionInterface
{
    public function getRequest(): RequestInterface;
}
