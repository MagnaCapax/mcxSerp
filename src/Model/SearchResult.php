<?php

declare(strict_types=1);

namespace MagnaCapax\Serp\Model;

use MagnaCapax\Serp\Enum\Device;
use MagnaCapax\Serp\Enum\Engine;

final class SearchResult implements \JsonSerializable
{
    public function __construct(
        public readonly string $query,
        public readonly Engine $engine,
        public readonly Device $device,
        public readonly string $provider,
        public readonly \DateTimeImmutable $timestamp,
        public readonly array $organicResults,
        public readonly SerpFeatures $features,
        public readonly ?string $country = null,
        public readonly ?string $language = null,
        public readonly ?int $totalResults = null,
        public readonly int $page = 1,
        public readonly array $rawResponse = [],
    ) {
    }

    public function findPosition(string $domain): ?int
    {
        foreach ($this->organicResults as $result) {
            if (str_contains($result->url, $domain)) {
                return $result->position;
            }
        }

        return null;
    }

    public function jsonSerialize(): array
    {
        return [
            'query' => $this->query,
            'engine' => $this->engine->value,
            'device' => $this->device->value,
            'provider' => $this->provider,
            'timestamp' => $this->timestamp->format('c'),
            'country' => $this->country,
            'language' => $this->language,
            'total_results' => $this->totalResults,
            'page' => $this->page,
            'organic_results' => array_map(static fn (OrganicResult $result): array => $result->jsonSerialize(), $this->organicResults),
            'features' => $this->features->jsonSerialize(),
            'raw_response' => $this->rawResponse,
        ];
    }
}
