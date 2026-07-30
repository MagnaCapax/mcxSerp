<?php

declare(strict_types=1);

namespace MagnaCapax\Serp\Provider;

use MagnaCapax\Serp\Enum\Device;
use MagnaCapax\Serp\Enum\Engine;
use MagnaCapax\Serp\Exception\AuthenticationException;
use MagnaCapax\Serp\Exception\NetworkException;
use MagnaCapax\Serp\Exception\ProviderException;
use MagnaCapax\Serp\Exception\RateLimitException;
use MagnaCapax\Serp\Exception\UnsupportedEngineException;
use MagnaCapax\Serp\Http\CurlHttpClient;
use MagnaCapax\Serp\Http\Request;
use MagnaCapax\Serp\Model\OrganicResult;
use MagnaCapax\Serp\Model\SearchResult;
use MagnaCapax\Serp\Model\SerpFeatures;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;

final class SerpApiProvider implements ProviderInterface
{
    private string $baseUrl;

    public function __construct(
        private readonly string $apiKey,
        ?ClientInterface $httpClient = null,
        array $options = [],
    ) {
        $this->httpClient = $httpClient ?? new CurlHttpClient((int) ($options['timeout'] ?? 30));
        $this->baseUrl = (string) ($options['baseUrl'] ?? 'https://serpapi.com/search');
    }

    private ClientInterface $httpClient;

    public function getName(): string
    {
        return 'serpapi';
    }

    public function getSupportedEngines(): array
    {
        return [Engine::Google, Engine::Bing, Engine::DuckDuckGo, Engine::YouTube];
    }

    public function search(string $query, Engine $engine = Engine::Google, array $options = []): SearchResult
    {
        if (!in_array($engine, $this->getSupportedEngines(), true)) {
            throw new UnsupportedEngineException('SerpAPI does not support engine ' . $engine->value . '.');
        }

        $device = $options['device'] ?? Device::Desktop;
        if (!$device instanceof Device) {
            $device = Device::from((string) $device);
        }

        $page = max(1, (int) ($options['page'] ?? 1));
        $params = [
            'api_key' => $this->apiKey,
            'engine' => $engine->value,
            'device' => $device->value,
            'no_cache' => !empty($options['no_cache']) ? 'true' : 'false',
        ];

        $queryKey = $engine === Engine::YouTube ? 'search_query' : 'q';
        $params[$queryKey] = $query;

        if (isset($options['language'])) {
            $params['hl'] = $options['language'];
        }
        if (isset($options['country'])) {
            $params['gl'] = $options['country'];
        }
        if (isset($options['location'])) {
            $params['location'] = $options['location'];
        }
        if ($page > 1) {
            $params['start'] = ($page - 1) * (int) ($options['numResults'] ?? 10);
        }
        if (isset($options['numResults'])) {
            $params['num'] = (int) $options['numResults'];
        }

        foreach ($options as $key => $value) {
            if (array_key_exists($key, [
                'device' => true,
                'country' => true,
                'language' => true,
                'location' => true,
                'page' => true,
                'numResults' => true,
                'resolve_ai_overview' => true,
            ])) {
                continue;
            }

            if (!array_key_exists($key, $params) && is_scalar($value)) {
                $params[$key] = (string) $value;
            }
        }

        $response = $this->send($this->buildRequest($params));
        $data = $this->decode($response);

        $features = $this->extractFeatures($data);
        $shouldResolveAiOverview = $options['resolve_ai_overview'] ?? true;

        if ($shouldResolveAiOverview && isset($data['ai_overview']['page_token']) && empty($data['ai_overview']['text_blocks'])) {
            $features['ai_overview'] = $this->resolveAiOverview((string) $data['ai_overview']['page_token']);
        }

        return new SearchResult(
            query: $query,
            engine: $engine,
            device: $device,
            provider: $this->getName(),
            timestamp: new \DateTimeImmutable(),
            organicResults: $this->normalizeOrganicResults($data['organic_results'] ?? []),
            features: new SerpFeatures($features),
            country: $options['country'] ?? null,
            language: $options['language'] ?? null,
            totalResults: isset($data['search_information']['total_results']) ? (int) $data['search_information']['total_results'] : null,
            page: $page,
            rawResponse: $data,
        );
    }

    private function buildRequest(array $params): Request
    {
        return new Request('GET', $this->baseUrl . '?' . http_build_query($params));
    }

    private function send(Request $request): ResponseInterface
    {
        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            throw new NetworkException($exception->getMessage(), 0, $exception);
        }

        return $this->assertResponse($response);
    }

    private function assertResponse(ResponseInterface $response): ResponseInterface
    {
        $status = $response->getStatusCode();

        return match (true) {
            $status === 401, $status === 403 => throw new AuthenticationException('Authentication failed for SerpAPI.'),
            $status === 429 => throw new RateLimitException('SerpAPI rate limit exceeded.', $this->parseRetryAfter($response)),
            $status >= 500 => throw new ProviderException('SerpAPI provider error.'),
            // Any other non-2xx is an error, NOT an empty result set — see SerperProvider for
            // the production incident this prevents (quota-exhausted 400 decoding to zero
            // organic results, indistinguishable from "the domain does not rank").
            $status < 200, $status >= 300 => throw new ProviderException($this->errorMessage('SerpAPI', $response)),
            default => $response,
        };
    }

    /**
     * Build an error message that surfaces the provider's own explanation when it sends one,
     * so an operator sees the cause rather than a bare status code.
     */
    private function errorMessage(string $provider, ResponseInterface $response): string
    {
        $status = $response->getStatusCode();
        $decoded = json_decode((string) $response->getBody(), true);
        $detail = is_array($decoded)
            ? ($decoded['error'] ?? $decoded['message'] ?? null)
            : null;

        return is_string($detail) && $detail !== ''
            ? sprintf('%s returned HTTP %d: %s', $provider, $status, $detail)
            : sprintf('%s returned HTTP %d.', $provider, $status);
    }

    private function parseRetryAfter(ResponseInterface $response): ?int
    {
        $value = $response->getHeaderLine('Retry-After');

        return $value !== '' && is_numeric($value) ? (int) $value : null;
    }

    private function decode(ResponseInterface $response): array
    {
        $data = json_decode((string) $response->getBody(), true);

        return is_array($data) ? $data : [];
    }

    private function normalizeOrganicResults(array $organicResults): array
    {
        $results = [];

        foreach ($organicResults as $item) {
            $results[] = new OrganicResult(
                position: (int) ($item['position'] ?? count($results) + 1),
                title: (string) ($item['title'] ?? ''),
                url: (string) ($item['link'] ?? ''),
                snippet: $item['snippet'] ?? null,
                displayedUrl: $item['displayed_link'] ?? null,
                date: $item['date'] ?? null,
                sitelinks: $this->normalizeSitelinks($item['sitelinks'] ?? []),
                extra: array_filter([
                    'rich_snippet' => $item['rich_snippet'] ?? null,
                ], static fn (mixed $value): bool => $value !== null),
            );
        }

        return $results;
    }

    private function normalizeSitelinks(array $sitelinks): array
    {
        $normalized = [];
        $inline = $sitelinks['inline'] ?? $sitelinks;

        foreach ($inline as $item) {
            if (!is_array($item)) {
                continue;
            }

            $normalized[] = [
                'title' => (string) ($item['title'] ?? ''),
                'link' => (string) ($item['link'] ?? ''),
            ];
        }

        return $normalized;
    }

    private function extractFeatures(array $data): array
    {
        $features = [];

        if (!empty($data['ai_overview'])) {
            $features['ai_overview'] = $this->normalizeAiOverview($data['ai_overview']);
        }

        if (!empty($data['answer_box'])) {
            $features['featured_snippet'] = [
                'title' => $data['answer_box']['title'] ?? ($data['answer_box']['source'] ?? null),
                'link' => $data['answer_box']['link'] ?? null,
                'snippet' => $data['answer_box']['snippet'] ?? ($data['answer_box']['answer'] ?? null),
                'domain' => isset($data['answer_box']['link']) ? parse_url((string) $data['answer_box']['link'], PHP_URL_HOST) : null,
                'type' => $data['answer_box']['type'] ?? null,
                'list' => $data['answer_box']['list'] ?? null,
            ];
        }

        if (!empty($data['related_questions'])) {
            $features['people_also_ask'] = array_map(
                static fn (array $item): array => [
                    'question' => (string) ($item['question'] ?? ''),
                    'snippet' => $item['snippet'] ?? null,
                    'link' => $item['link'] ?? null,
                    'title' => $item['title'] ?? null,
                ],
                $data['related_questions']
            );
        }

        if (!empty($data['knowledge_graph'])) {
            $features['knowledge_graph'] = [
                'title' => $data['knowledge_graph']['title'] ?? '',
                'type' => $data['knowledge_graph']['type'] ?? null,
                'description' => $data['knowledge_graph']['description'] ?? null,
                'kgmid' => $data['knowledge_graph']['kgmid'] ?? null,
                'source' => $data['knowledge_graph']['source'] ?? null,
            ];
        }

        if (!empty($data['related_searches'])) {
            $features['related_searches'] = array_map(
                static fn (array $item): array => [
                    'query' => (string) ($item['query'] ?? ''),
                    'link' => $item['link'] ?? null,
                ],
                $data['related_searches']
            );
        }

        if (!empty($data['ads'])) {
            $features['ads'] = $data['ads'];
        }

        if (!empty($data['shopping_results'])) {
            $features['shopping_results'] = $data['shopping_results'];
        }

        return $features;
    }

    private function normalizeAiOverview(array $overview): ?array
    {
        $textBlocks = $overview['text_blocks'] ?? [];
        if ($textBlocks === [] && !isset($overview['page_token'])) {
            return null;
        }

        $textParts = [];
        $references = [];

        foreach ($textBlocks as $block) {
            if (isset($block['snippet']) && $block['snippet'] !== '') {
                $textParts[] = trim((string) $block['snippet']);
            }

            foreach ($block['references'] ?? [] as $reference) {
                $references[] = [
                    'title' => $reference['title'] ?? '',
                    'link' => $reference['link'] ?? '',
                    'source' => $reference['source'] ?? null,
                ];
            }
        }

        return [
            'text' => trim(implode("\n\n", array_filter($textParts))),
            'references' => $references,
            'page_token' => $overview['page_token'] ?? null,
        ];
    }

    private function resolveAiOverview(string $pageToken): ?array
    {
        $response = $this->send($this->buildRequest([
            'engine' => 'google_ai_overview',
            'page_token' => $pageToken,
            'api_key' => $this->apiKey,
        ]));

        return $this->normalizeAiOverview($this->decode($response));
    }
}
