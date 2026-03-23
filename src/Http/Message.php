<?php

declare(strict_types=1);

namespace MagnaCapax\Serp\Http;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;

abstract class Message implements MessageInterface
{
    protected string $protocolVersion = '1.1';
    protected array $headers = [];
    protected StreamInterface $body;

    public function __construct(array $headers = [], ?StreamInterface $body = null)
    {
        foreach ($headers as $name => $value) {
            $this->headers[strtolower($name)] = [
                'original' => $name,
                'values' => is_array($value) ? array_values($value) : [$value],
            ];
        }

        $this->body = $body ?? Stream::fromString('');
    }

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function withProtocolVersion(string $version): static
    {
        $clone = clone $this;
        $clone->protocolVersion = $version;

        return $clone;
    }

    public function getHeaders(): array
    {
        $headers = [];
        foreach ($this->headers as $header) {
            $headers[$header['original']] = $header['values'];
        }

        return $headers;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[strtolower($name)]);
    }

    public function getHeader(string $name): array
    {
        return $this->headers[strtolower($name)]['values'] ?? [];
    }

    public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    public function withHeader(string $name, $value): static
    {
        $clone = clone $this;
        $clone->headers[strtolower($name)] = [
            'original' => $name,
            'values' => is_array($value) ? array_values($value) : [$value],
        ];

        return $clone;
    }

    public function withAddedHeader(string $name, $value): static
    {
        $clone = clone $this;
        $key = strtolower($name);
        $existing = $clone->headers[$key]['values'] ?? [];
        $clone->headers[$key] = [
            'original' => $name,
            'values' => array_merge($existing, is_array($value) ? array_values($value) : [$value]),
        ];

        return $clone;
    }

    public function withoutHeader(string $name): static
    {
        $clone = clone $this;
        unset($clone->headers[strtolower($name)]);

        return $clone;
    }

    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    public function withBody(StreamInterface $body): static
    {
        $clone = clone $this;
        $clone->body = $body;

        return $clone;
    }
}
