# mcxSerp

Multi-provider SERP API client for PHP with a unified interface for Serper.dev and SerpAPI.

## Installation

```bash
composer require magnacapax/mcx-serp
```

## Quick start

```php
use MagnaCapax\Serp\Provider\SerpApiProvider;

$provider = new SerpApiProvider('your-api-key');
$result = $provider->search('site:example.com llm monitoring');
echo $result->findPosition('example.com');
```

## Provider comparison

| Provider | Engines | Free tier | Auth |
| --- | --- | --- | --- |
| Serper.dev | Google | 2,500/month | `X-API-KEY` header |
| SerpAPI | Google, Bing, DuckDuckGo, YouTube | 250/month | `api_key` query parameter |

## API reference

### `ProviderInterface`

- `getName(): string`
- `getSupportedEngines(): array`
- `search(string $query, Engine $engine = Engine::Google, array $options = []): SearchResult`

### `SearchResult`

- `query`
- `engine`
- `device`
- `provider`
- `timestamp`
- `organicResults`
- `features`
- `country`
- `language`
- `totalResults`
- `page`
- `rawResponse`
- `findPosition(string $domain): ?int`

### `OrganicResult`

- `position`
- `title`
- `url`
- `snippet`
- `displayedUrl`
- `date`
- `sitelinks`
- `extra`
- `getDomain(): string`

### `SerpFeatures`

- `has(string $type): bool`
- `get(string $type): mixed`
- `all(): array`
- `getAiOverview(): ?array`
- `getFeaturedSnippet(): ?array`
- `getPeopleAlsoAsk(): array`
- `getKnowledgeGraph(): ?array`
- `getRelatedSearches(): array`
- `getAds(): array`
- `isDomainInAiOverview(string $domain): bool`
- `isDomainInFeaturedSnippet(string $domain): bool`

## Adding a custom provider

Implement [`ProviderInterface`](src/Provider/ProviderInterface.php) and return a normalized [`SearchResult`](src/Model/SearchResult.php) from `search()`.

## License

Apache-2.0
