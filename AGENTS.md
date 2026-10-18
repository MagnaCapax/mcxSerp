# mcxSerp — Multi-Provider SERP API Client for PHP

## What This Is

A PHP library that provides a unified interface to query multiple SERP (Search Engine Results Page) APIs. One interface, multiple providers, normalized results.

**This is a LIBRARY, not an application.** No CLI, no storage, no alerting, no config files. Consumers build those on top.

## License

Apache-2.0. No MIT. No exceptions.

## Principles

Start with [`development/`](development/README.md): the [engineering principles](development/engineering-principles.md) explain these rules with their reasons and the mistakes that taught them, and [ADR-0001](docs/adr/0001-architecture-layout-and-languages.md) records this library's layout and languages.

KISS, DRY, YAGNI ("What NOT to Build" below is this rule applied). Delete first: question the requirement, delete the part, simplify, then speed up, then automate (a needed but broken part is debugged, not deleted). Pit of success: safe defaults, risky paths explicit and loud; a failed call raises a typed exception and is never returned as an empty result. Never break old users: `ProviderInterface`, the public model properties, the `jsonSerialize()` keys and the exception classes are the public API. Tests that can fail: run `php tests/run.php`, read its summary and exit code, and give a provider change a fixture and a test for its error paths. Files around 150 lines (a target, not a hard limit). camelCase names read from the broadest context to the most specific; class and file names follow PSR-4. Accepted ADRs in `docs/adr/` are binding: follow them or supersede them with a new ADR, never work around them; a significant behaviour, interface, security or workflow change ships with an ADR (template `docs/adr/0000-template.md`, one subject per ADR). Before committing, review the change with the [review unit](https://github.com/MagnaCapax/mcxPikkumies/tree/main/pikkumies/tools/development/code-review) against these rules and the ADRs. This repository is public: no API keys, credentials or internal data in code, tests, fixtures or commit messages.

## Tech Stack

- PHP 8.1+ (uses backed enums, readonly properties, constructor promotion)
- PSR-18 HTTP Client interface (`psr/http-client`) — consumers provide their own HTTP client (Guzzle, Symfony HttpClient, etc.)
- Includes a zero-dependency fallback CurlHttpClient for standalone use
- PHPUnit for tests
- No framework dependencies

## Architecture

```
mcxSerp/
├── src/
│   ├── Provider/
│   │   ├── ProviderInterface.php      # The contract
│   │   ├── SerperProvider.php         # Serper.dev (Google only, 2500 free/mo)
│   │   └── SerpApiProvider.php        # SerpAPI (Google, Bing, DDG, YouTube, 250 free/mo)
│   ├── Model/
│   │   ├── SearchResult.php           # Normalized result from any provider
│   │   ├── OrganicResult.php          # Single organic search result
│   │   └── SerpFeatures.php           # SERP features (AI overview, snippets, PAA, etc.)
│   ├── Enum/
│   │   ├── Engine.php                 # Google, Bing, DuckDuckGo, YouTube, Yahoo
│   │   └── Device.php                 # Desktop, Mobile, Tablet
│   ├── Exception/
│   │   ├── SerpException.php          # Base exception
│   │   ├── AuthenticationException.php
│   │   ├── RateLimitException.php
│   │   ├── ProviderException.php
│   │   ├── UnsupportedEngineException.php
│   │   └── NetworkException.php
│   └── Http/
│       └── CurlHttpClient.php         # Zero-dep PSR-18 fallback
├── tests/
│   ├── Provider/
│   │   ├── SerperProviderTest.php
│   │   └── SerpApiProviderTest.php
│   ├── Model/
│   │   ├── SearchResultTest.php
│   │   └── SerpFeaturesTest.php
│   ├── Http/
│   │   └── MockHttpClient.php         # PSR-18 mock for testing
│   └── fixtures/
│       ├── serper/
│       │   ├── search-success.json
│       │   └── search-with-features.json
│       └── serpapi/
│           ├── google-search.json
│           ├── google-ai-overview-inline.json
│           ├── google-ai-overview-lazy.json
│           ├── bing-search.json
│           ├── duckduckgo-search.json
│           └── youtube-search.json
├── composer.json
├── phpunit.xml
├── LICENSE                             # Apache-2.0
└── README.md
```

## Namespace

`MagnaCapax\Serp\`

## Composer Package

```json
{
  "name": "magnacapax/mcx-serp",
  "description": "Multi-provider SERP API client for PHP — unified interface to Serper.dev, SerpAPI, and more",
  "type": "library",
  "license": "Apache-2.0",
  "require": {
    "php": ">=8.1",
    "psr/http-client": "^1.0",
    "psr/http-factory": "^1.0",
    "psr/http-message": "^1.1|^2.0"
  },
  "require-dev": {
    "phpunit/phpunit": "^10.0|^11.0",
    "guzzlehttp/guzzle": "^7.0"
  },
  "suggest": {
    "guzzlehttp/guzzle": "PSR-18 HTTP client implementation",
    "symfony/http-client": "Alternative PSR-18 HTTP client implementation"
  },
  "autoload": {
    "psr-4": { "MagnaCapax\\Serp\\": "src/" }
  },
  "autoload-dev": {
    "psr-4": { "MagnaCapax\\Serp\\Tests\\": "tests/" }
  }
}
```

## Enums

### Engine (backed enum)

```php
namespace MagnaCapax\Serp\Enum;

enum Engine: string
{
    case Google = 'google';
    case Bing = 'bing';
    case DuckDuckGo = 'duckduckgo';
    case YouTube = 'youtube';
    case Yahoo = 'yahoo';
}
```

### Device (backed enum)

```php
namespace MagnaCapax\Serp\Enum;

enum Device: string
{
    case Desktop = 'desktop';
    case Mobile = 'mobile';
    case Tablet = 'tablet';
}
```

## Provider Interface

```php
namespace MagnaCapax\Serp\Provider;

use MagnaCapax\Serp\Enum\Engine;
use MagnaCapax\Serp\Model\SearchResult;

interface ProviderInterface
{
    /** Provider name (e.g., "serper", "serpapi") */
    public function getName(): string;

    /** @return Engine[] Engines this provider supports */
    public function getSupportedEngines(): array;

    /**
     * Run a search query and return normalized results.
     *
     * @param string $query    Search query (supports site:, intitle:, etc.)
     * @param Engine $engine   Search engine to query
     * @param array  $options  {
     *     device?: Device,
     *     country?: string (2-letter: "us", "fi"),
     *     language?: string (2-letter: "en", "fi"),
     *     page?: int (1-based),
     *     numResults?: int,
     *     ...provider-specific options passed through
     * }
     * @return SearchResult
     * @throws UnsupportedEngineException If engine not supported by this provider
     * @throws AuthenticationException On 401/403
     * @throws RateLimitException On 429
     * @throws ProviderException On 500/503
     * @throws NetworkException On connection failure
     */
    public function search(string $query, Engine $engine = Engine::Google, array $options = []): SearchResult;
}
```

## Models

### SearchResult

```php
namespace MagnaCapax\Serp\Model;

use MagnaCapax\Serp\Enum\Engine;
use MagnaCapax\Serp\Enum\Device;

final class SearchResult implements \JsonSerializable
{
    public function __construct(
        public readonly string $query,
        public readonly Engine $engine,
        public readonly Device $device,
        public readonly string $provider,
        public readonly \DateTimeImmutable $timestamp,
        /** @var OrganicResult[] */
        public readonly array $organicResults,
        public readonly SerpFeatures $features,
        public readonly ?string $country = null,
        public readonly ?string $language = null,
        public readonly ?int $totalResults = null,
        public readonly int $page = 1,
        /** Raw provider response for anything the normalized model doesn't cover */
        public readonly array $rawResponse = [],
    ) {}

    /** Find position of a domain in organic results. Returns null if not found. */
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
            'organic_results' => array_map(fn($r) => $r->jsonSerialize(), $this->organicResults),
            'features' => $this->features->jsonSerialize(),
            'raw_response' => $this->rawResponse,
        ];
    }
}
```

### OrganicResult

```php
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
        /** @var array{title: string, link: string}[] */
        public readonly array $sitelinks = [],
        /** Provider-specific extras not in the normalized model */
        public readonly array $extra = [],
    ) {}

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
```

### SerpFeatures

Flexible container with typed accessors for common features, plus `has()/get()` for anything else.

```php
namespace MagnaCapax\Serp\Model;

final class SerpFeatures implements \JsonSerializable
{
    /**
     * @param array<string, mixed> $features All detected features keyed by type string.
     *   Known types: ai_overview, featured_snippet, people_also_ask, knowledge_graph,
     *   related_searches, ads, shopping_results, video_carousel, image_pack,
     *   local_pack, top_stories, discussions
     */
    public function __construct(
        private readonly array $features = [],
    ) {}

    /** Check if a feature type is present */
    public function has(string $type): bool
    {
        return isset($this->features[$type]) && !empty($this->features[$type]);
    }

    /** Get raw feature data by type */
    public function get(string $type): mixed
    {
        return $this->features[$type] ?? null;
    }

    /** Get all features */
    public function all(): array
    {
        return $this->features;
    }

    // Typed convenience accessors

    /** @return ?array{text: string, references: array} */
    public function getAiOverview(): ?array { return $this->get('ai_overview'); }

    /** @return ?array{title: string, link: string, snippet: string, domain: ?string} */
    public function getFeaturedSnippet(): ?array { return $this->get('featured_snippet'); }

    /** @return array{question: string, snippet: ?string, link: ?string}[] */
    public function getPeopleAlsoAsk(): array { return $this->get('people_also_ask') ?? []; }

    /** @return ?array{title: string, type: ?string, description: ?string} */
    public function getKnowledgeGraph(): ?array { return $this->get('knowledge_graph'); }

    /** @return array{query: string}[] */
    public function getRelatedSearches(): array { return $this->get('related_searches') ?? []; }

    /** @return array */
    public function getAds(): array { return $this->get('ads') ?? []; }

    /** Does a specific domain appear in AI overview references? */
    public function isDomainInAiOverview(string $domain): bool
    {
        $ao = $this->getAiOverview();
        if (!$ao || empty($ao['references'])) return false;
        foreach ($ao['references'] as $ref) {
            if (isset($ref['link']) && str_contains($ref['link'], $domain)) return true;
        }
        return false;
    }

    /** Does a specific domain own the featured snippet? */
    public function isDomainInFeaturedSnippet(string $domain): bool
    {
        $fs = $this->getFeaturedSnippet();
        if (!$fs || !isset($fs['link'])) return false;
        return str_contains($fs['link'], $domain);
    }

    public function jsonSerialize(): array
    {
        return $this->features;
    }
}
```

## Exception Hierarchy

```
MagnaCapax\Serp\Exception\SerpException (extends \RuntimeException)
├── AuthenticationException          # 401/403 — bad API key
├── RateLimitException               # 429 — add retryAfterSeconds property
├── ProviderException                # 500/503 — provider server error
├── UnsupportedEngineException       # Provider doesn't support requested engine
└── NetworkException                 # Timeout, DNS failure, connection refused
```

`RateLimitException` should include:
- `retryAfterSeconds: ?int` (from Retry-After header if available)

## Provider Implementation Details

### SerperProvider

- **Supported engines**: Google only
- **Base URL**: `https://google.serper.dev`
- **Auth**: `X-API-KEY: {key}` header on every request
- **Method**: POST with JSON body to `/search`
- **Request body**: `{"q": "query", "gl": "us", "hl": "en", "num": 10, "device": "desktop"}`
- **Key request params**: q (required), gl, hl, num (10/20/50), device, tbs, page, autocorrect
- **Response field mapping**:
  - `organic[]` → OrganicResult[] — fields: title, link, snippet, position, sitelinks, date
  - `answerBox` → features['featured_snippet'] — fields: snippet, title, link
  - `peopleAlsoAsk[]` → features['people_also_ask'] — fields: question, snippet, link
  - `knowledgeGraph` → features['knowledge_graph'] — fields: title, type, website, description, attributes
  - `relatedSearches[]` → features['related_searches'] — fields: query
- **Error handling**: HTTP 429 = RateLimitException, 401 = AuthenticationException, 5xx = ProviderException
- **Rate limits**: 2,500 free/month, strict QPS on free tier (undocumented exact limit — implement backoff on 429)

### SerpApiProvider

- **Supported engines**: Google, Bing, DuckDuckGo, YouTube
- **Base URL**: `https://serpapi.com/search`
- **Auth**: `api_key` query parameter
- **Method**: GET with query parameters
- **Engine selection**: `engine` parameter
- **YouTube quirk**: Uses `search_query` param instead of `q` — the provider MUST handle this transparently
- **Key request params**: q, engine, location, hl, gl, device, start, no_cache
- **Response field mapping**:
  - `organic_results[]` → OrganicResult[] — fields: position, title, link, snippet, displayed_link, sitelinks, date, rich_snippet
  - `ai_overview` → features['ai_overview']
    - **CRITICAL: Two paths for AI Overview.**
      1. Inline: `ai_overview.text_blocks` populated directly → extract text + references
      2. Lazy-loaded: Only `ai_overview.page_token` returned → must make second GET to `https://serpapi.com/search?engine=google_ai_overview&page_token={token}&api_key={key}`. Token expires in ~1 minute. Make the request immediately.
    - If `ai_overview` exists but has `page_token` and no `text_blocks`: auto-resolve by default. Add `resolve_ai_overview` option (default true) to allow disabling.
  - `answer_box` → features['featured_snippet'] — fields: type, title, link, snippet, list
  - `related_questions[]` → features['people_also_ask'] — fields: question, snippet, title, link
  - `knowledge_graph` → features['knowledge_graph'] — fields: title, type, kgmid, description, source
  - `related_searches[]` → features['related_searches'] — fields: query, link
  - `ads[]` → features['ads'] — fields: block_position, position, title, link, description
  - `shopping_results[]` → features['shopping_results'] — fields: title, price, source, link, rating
- **Caching**: SerpAPI caches results for 1 hour; cached results are free (don't count toward quota). Default: use cache. `no_cache` option to bypass.
- **Rate limits**: 250 free/month, 50/hour on free tier. Only successful searches count.

## HTTP Client

### CurlHttpClient (zero-dependency PSR-18 fallback)

A minimal PSR-18 (`Psr\Http\Client\ClientInterface`) implementation using PHP's built-in curl extension. This allows the library to work without Guzzle or any other HTTP library installed.

It needs to:
- Implement `ClientInterface::sendRequest(RequestInterface $request): ResponseInterface`
- Support GET and POST methods
- Support custom headers
- Support JSON request bodies
- Handle timeouts (default 30s, configurable)
- Return proper PSR-7 responses

For the PSR-7 message implementations (Request, Response, Uri, Stream), include minimal implementations or use `nyholm/psr7` as a suggested dependency. A lightweight approach: bundle a minimal PSR-7 implementation in `src/Http/` (Message.php, Request.php, Response.php, Stream.php, Uri.php) — just enough to satisfy PSR-18 without external dependencies.

### Provider constructors

Providers take PSR-18 client in constructor:
```php
public function __construct(
    string $apiKey,
    ?ClientInterface $httpClient = null,  // null = use CurlHttpClient
    array $options = []  // timeout, baseUrl override, etc.
)
```

## Testing Strategy

- Mock HTTP responses using `MockHttpClient` (PSR-18 implementation that returns canned responses)
- Fixture JSON files: real API responses saved from actual API calls
- Test each provider normalizes fixtures correctly into SearchResult/OrganicResult/SerpFeatures
- Test SerpFeatures::has/get/isDomainIn* methods
- Test SearchResult::findPosition
- Test exception throwing on error responses (401, 429, 500)
- Test SerpApiProvider handles both inline and lazy-loaded AI overviews
- Test SerpApiProvider transparently maps `search_query` for YouTube engine

## What NOT to Build

- No CLI binary — this is a library
- No config file loading — consumers configure via constructor args
- No storage/persistence — consumers handle their own storage
- No alerting/monitoring logic — consumers build their own
- No notification system
- No cron scheduling
- No DataForSEO provider yet (add later; the interface supports it)
- No abstract base class for providers — just the interface + concrete implementations
- No batch/searchBatch method yet — start with single search, add batch if needed

## README Structure

The README.md should include:
1. One-line description
2. Installation (composer require)
3. Quick start (3-line example: create provider, search, read results)
4. Provider comparison table (engines supported, free tier, auth method)
5. Full API reference (ProviderInterface methods, model properties)
6. Adding a custom provider (implement ProviderInterface)
7. License (Apache-2.0)
