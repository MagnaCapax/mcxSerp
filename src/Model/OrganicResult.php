<?php

declare(strict_types=1);

namespace MagnaCapax\Serp\Model;

final class OrganicResult implements \JsonSerializable
{
    public function __construct(
        public readonly int $position,
        public readonly string $title,
        public readonly string $url,
        public readonly ?string $snippet = null,
        public readonly ?string $displayedUrl = null,
        public readonly ?string $date = null,
        public readonly array $sitelinks = [],
        public readonly array $extra = [],
    ) {
    }

    public function getDomain(): string
    {
        return parse_url($this->url, PHP_URL_HOST) ?? '';
    }

    public function jsonSerialize(): array
    {
        return [
            'position' => $this->position,
            'title' => $this->title,
            'url' => $this->url,
            'domain' => $this->getDomain(),
            'snippet' => $this->snippet,
            'displayed_url' => $this->displayedUrl,
            'date' => $this->date,
            'sitelinks' => $this->sitelinks,
            'extra' => $this->extra,
        ];
    }
}
