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
use MagnaCapax\Serp\Http\Stream;
use MagnaCapax\Serp\Model\OrganicResult;
use MagnaCapax\Serp\Model\SearchResult;
use MagnaCapax\Serp\Model\SerpFeatures;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;

final class SerperProvider implements ProviderInterface
{
    private string $baseUrl;

    public function __construct(
        private readonly string $apiKey,
        ?ClientInterface $httpClient = null,
        array $options = [],
    ) {
        $this->httpClient = $httpClient ?? new CurlHttpClient((int) ($options['timeout'] ?? 30));
        $this->baseUrl = rtrim((string) ($options['baseUrl'] ?? 'https://google.serper.dev'), '/');
    }

    private ClientInterface $httpClient;

    public function getName(): string
    {
        return 'serper';
    }

    public function getSupportedEngines(): array
    {
        return [Engine::Google];
    }

    public function search(string $query, Engine $engine = Engine::Google, array $options = []): SearchResult
    {
        if ($engine !== Engine::Google) {
            throw new UnsupportedEngineException('Serper supports Google only.');
        }

        $device = $options['device'] ?? Device::Desktop;
        if (!$device instanceof Device) {
            $device = Device::from((string) $device);
        }

        $page = max(1, (int) ($options['page'] ?? 1));
        $payload = ['q' => $query];

        if (isset($options['country'])) {
            $payload['gl'] = $options['country'];
        }
        if (isset($options['language'])) {
            $payload['hl'] = $options['language'];
        }
        if (isset($options['numResults'])) {
            $payload['num'] = (int) $options['numResults'];
        }

        $payload['device'] = $device->value;

        if ($page > 1) {
            $payload['page'] = $page;
        }

        foreach ($options as $key => $value) {
            if (array_key_exists($key, [
                'device' => true,
                'country' => true,
                'language' => true,
                'page' => true,
                'numResults' => true,
            ])) {
                continue;
            }

            if (!array_key_exists($key, $payload) && is_scalar($value)) {
                $payload[$key] = $value;
            }
        }

        $request = new Request(
            'POST',
            $this->baseUrl . '/search',
            [
                'X-API-KEY' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            Stream::fromString((string) json_encode($payload, JSON_THROW_ON_ERROR))
        );

        $response = $this->send($request);
        $data = $this->decode($response);

        return new SearchResult(
            query: $query,
            engine: $engine,
            device: $device,
            provider: $this->getName(),
            timestamp: new \DateTimeImmutable(),
            organicResults: $this->normalizeOrganicResults($data['organic'] ?? []),
            features: new SerpFeatures($this->extractFeatures($data)),
            country: $options['country'] ?? null,
            language: $options['language'] ?? null,
            totalResults: isset($data['searchInformation']['totalResults']) ? (int) $data['searchInformation']['totalResults'] : null,
            page: $page,
            rawResponse: $data,
        );
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
            $status === 401, $status === 403 => throw new AuthenticationException('Authentication failed for Serper.'),
            $status === 429 => throw new RateLimitException('Serper rate limit exceeded.', $this->parseRetryAfter($response)),
            $status >= 500 => throw new ProviderException('Serper provider error.'),
            default => $response,
        };
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
                displayedUrl: $item['displayedLink'] ?? null,
                date: $item['date'] ?? null,
                sitelinks: array_map(
                    static fn (array $sitelink): array => [
                        'title' => (string) ($sitelink['title'] ?? ''),
                        'link' => (string) ($sitelink['link'] ?? ''),
                    ],
                    $item['sitelinks'] ?? []
                ),
                extra: [],
            );
        }

        return $results;
    }

    private function extractFeatures(array $data): array
    {
        $features = [];

        if (!empty($data['answerBox'])) {
            $features['featured_snippet'] = [
                'title' => $data['answerBox']['title'] ?? null,
                'link' => $data['answerBox']['link'] ?? null,
                'snippet' => $data['answerBox']['snippet'] ?? ($data['answerBox']['answer'] ?? null),
                'domain' => isset($data['answerBox']['link']) ? parse_url((string) $data['answerBox']['link'], PHP_URL_HOST) : null,
            ];
        }

        if (!empty($data['peopleAlsoAsk'])) {
            $features['people_also_ask'] = array_map(
                static fn (array $item): array => [
                    'question' => (string) ($item['question'] ?? ''),
                    'snippet' => $item['snippet'] ?? null,
                    'link' => $item['link'] ?? null,
                ],
                $data['peopleAlsoAsk']
            );
        }

        if (!empty($data['knowledgeGraph'])) {
            $features['knowledge_graph'] = [
                'title' => $data['knowledgeGraph']['title'] ?? '',
                'type' => $data['knowledgeGraph']['type'] ?? null,
                'description' => $data['knowledgeGraph']['description'] ?? null,
                'website' => $data['knowledgeGraph']['website'] ?? null,
                'attributes' => $data['knowledgeGraph']['attributes'] ?? [],
            ];
        }

        if (!empty($data['relatedSearches'])) {
            $features['related_searches'] = array_map(
                static fn (array $item): array => ['query' => (string) ($item['query'] ?? '')],
                $data['relatedSearches']
            );
        }

        return $features;
    }
}
