<?php

declare(strict_types=1);

namespace MagnaCapax\Serp\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

final class Response extends Message implements ResponseInterface
{
    public function __construct(
        private int $statusCode = 200,
        array $headers = [],
        ?StreamInterface $body = null,
        private string $reasonPhrase = '',
    ) {
        parent::__construct($headers, $body);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function withStatus(int $code, string $reasonPhrase = ''): static
    {
        $clone = clone $this;
        $clone->statusCode = $code;
        $clone->reasonPhrase = $reasonPhrase;

        return $clone;
    }

    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }
}
