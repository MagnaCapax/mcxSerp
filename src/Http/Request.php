<?php

declare(strict_types=1);

namespace MagnaCapax\Serp\Http;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

final class Request extends Message implements RequestInterface
{
    private string $requestTarget = '';

    public function __construct(
        private string $method,
        UriInterface|string $uri,
        array $headers = [],
        ?StreamInterface $body = null,
    ) {
        parent::__construct($headers, $body);
        $this->uri = is_string($uri) ? Uri::fromString($uri) : $uri;
        $this->method = strtoupper($method);
    }

    private UriInterface $uri;

    public function getRequestTarget(): string
    {
        if ($this->requestTarget !== '') {
            return $this->requestTarget;
        }

        $target = $this->uri->getPath();
        $query = $this->uri->getQuery();

        return $query !== '' ? $target . '?' . $query : $target;
    }

    public function withRequestTarget(string $requestTarget): static
    {
        $clone = clone $this;
        $clone->requestTarget = $requestTarget;

        return $clone;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function withMethod(string $method): static
    {
        $clone = clone $this;
        $clone->method = strtoupper($method);

        return $clone;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, bool $preserveHost = false): static
    {
        $clone = clone $this;
        $clone->uri = $uri;

        if (!$preserveHost && $uri->getHost() !== '') {
            $clone = $clone->withHeader('Host', $uri->getHost());
        }

        return $clone;
    }
}
