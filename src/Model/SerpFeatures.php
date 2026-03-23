<?php

declare(strict_types=1);

namespace MagnaCapax\Serp\Model;

final class SerpFeatures implements \JsonSerializable
{
    public function __construct(
        private readonly array $features = [],
    ) {
    }

    public function has(string $type): bool
    {
        return isset($this->features[$type]) && !empty($this->features[$type]);
    }

    public function get(string $type): mixed
    {
        return $this->features[$type] ?? null;
    }

    public function all(): array
    {
        return $this->features;
    }

    public function getAiOverview(): ?array
    {
        return $this->get('ai_overview');
    }

    public function getFeaturedSnippet(): ?array
    {
        return $this->get('featured_snippet');
    }

    public function getPeopleAlsoAsk(): array
    {
        return $this->get('people_also_ask') ?? [];
    }

    public function getKnowledgeGraph(): ?array
    {
        return $this->get('knowledge_graph');
    }

    public function getRelatedSearches(): array
    {
        return $this->get('related_searches') ?? [];
    }

    public function getAds(): array
    {
        return $this->get('ads') ?? [];
    }

    public function isDomainInAiOverview(string $domain): bool
    {
        $overview = $this->getAiOverview();
        if ($overview === null || empty($overview['references'])) {
            return false;
        }

        foreach ($overview['references'] as $reference) {
            if (isset($reference['link']) && str_contains($reference['link'], $domain)) {
                return true;
            }
        }

        return false;
    }

    public function isDomainInFeaturedSnippet(string $domain): bool
    {
        $featuredSnippet = $this->getFeaturedSnippet();
        if ($featuredSnippet === null || !isset($featuredSnippet['link'])) {
            return false;
        }

        return str_contains($featuredSnippet['link'], $domain);
    }

    public function jsonSerialize(): array
    {
        return $this->features;
    }
}
