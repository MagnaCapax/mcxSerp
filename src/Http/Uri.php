<?php

declare(strict_types=1);

namespace MagnaCapax\Serp\Http;

use Psr\Http\Message\UriInterface;

final class Uri implements UriInterface
{
    public function __construct(
        private string $scheme = '',
        private string $host = '',
        private ?int $port = null,
        private string $path = '',
        private string $query = '',
        private string $fragment = '',
        private string $user = '',
        private ?string $password = null,
    ) {
    }

    public static function fromString(string $uri): self
    {
        $parts = parse_url($uri);
        if ($parts === false) {
            throw new \InvalidArgumentException('Invalid URI: ' . $uri);
        }

        return new self(
            $parts['scheme'] ?? '',
            $parts['host'] ?? '',
            $parts['port'] ?? null,
            $parts['path'] ?? '',
            $parts['query'] ?? '',
            $parts['fragment'] ?? '',
            $parts['user'] ?? '',
            $parts['pass'] ?? null,
        );
    }

    public function getScheme(): string { return $this->scheme; }
    public function getAuthority(): string
    {
        if ($this->host === '') {
            return '';
        }

        $authority = $this->getUserInfo() !== '' ? $this->getUserInfo() . '@' : '';
        $authority .= $this->host;

        return $this->port !== null ? $authority . ':' . $this->port : $authority;
    }
    public function getUserInfo(): string
    {
        if ($this->user === '') {
            return '';
        }

        return $this->password === null ? $this->user : $this->user . ':' . $this->password;
    }
    public function getHost(): string { return $this->host; }
    public function getPort(): ?int { return $this->port; }
    public function getPath(): string { return $this->path; }
    public function getQuery(): string { return $this->query; }
    public function getFragment(): string { return $this->fragment; }
    public function withScheme(string $scheme): static { $clone = clone $this; $clone->scheme = strtolower($scheme); return $clone; }
    public function withUserInfo(string $user, ?string $password = null): static { $clone = clone $this; $clone->user = $user; $clone->password = $password; return $clone; }
    public function withHost(string $host): static { $clone = clone $this; $clone->host = strtolower($host); return $clone; }
    public function withPort(?int $port): static { $clone = clone $this; $clone->port = $port; return $clone; }
    public function withPath(string $path): static { $clone = clone $this; $clone->path = $path; return $clone; }
    public function withQuery(string $query): static { $clone = clone $this; $clone->query = ltrim($query, '?'); return $clone; }
    public function withFragment(string $fragment): static { $clone = clone $this; $clone->fragment = ltrim($fragment, '#'); return $clone; }

    public function __toString(): string
    {
        $uri = $this->scheme !== '' ? $this->scheme . ':' : '';
        $authority = $this->getAuthority();
        if ($authority !== '') {
            $uri .= '//' . $authority;
        }

        $uri .= $this->path;

        if ($this->query !== '') {
            $uri .= '?' . $this->query;
        }

        if ($this->fragment !== '') {
            $uri .= '#' . $this->fragment;
        }

        return $uri;
    }
}
