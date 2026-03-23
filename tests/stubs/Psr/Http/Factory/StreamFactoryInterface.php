<?php

declare(strict_types=1);

namespace Psr\Http\Factory;

use Psr\Http\Message\StreamInterface;

interface StreamFactoryInterface
{
    public function createStream(string $content = ''): StreamInterface;
}
