<?php

declare(strict_types=1);

namespace MagnaCapax\Serp\Tests\Provider;

use MagnaCapax\Serp\Enum\Engine;
use MagnaCapax\Serp\Exception\AuthenticationException;
use MagnaCapax\Serp\Exception\RateLimitException;
use MagnaCapax\Serp\Http\Response;
use MagnaCapax\Serp\Http\Stream;
use MagnaCapax\Serp\Provider\SerpApiProvider;
use MagnaCapax\Serp\Tests\Http\MockHttpClient;
use PHPUnit\Framework\TestCase;

final class SerpApiProviderTest extends TestCase
{
    public function testGoogleSearchNormalizesResultsAndFeatures(): void
    {
        $provider = new SerpApiProvider(
            'test-key',
            new MockHttpClient([
                new Response(200, ['Content-Type' => ['application/json']], Stream::fromString($this->fixture('serpapi/google-search.json'))),
            ])
        );

        $result = $provider->search('serp api', Engine::Google, ['country' => 'us', 'language' => 'en']);

        self::assertSame('serpapi', $result->provider);
        self::assertCount(2, $result->organicResults);
        self::assertSame('example.com › serp-api-guide', $result->organicResults[0]->displayedUrl);
        self::assertArrayHasKey('rich_snippet', $result->organicResults[0]->extra);
        self::assertTrue($result->features->has('featured_snippet'));
        self::assertTrue($result->features->has('ads'));
        self::assertSame('Search engine', $result->features->getKnowledgeGraph()['title']);
    }

    public function testInlineAiOverviewIsExtracted(): void
    {
        $provider = new SerpApiProvider(
            'test-key',
            new MockHttpClient([
                new Response(200, [], Stream::fromString($this->fixture('serpapi/google-ai-overview-inline.json'))),
            ])
        );

        $result = $provider->search('ai overviews', Engine::Google);
        $overview = $result->features->getAiOverview();

        self::assertStringContainsString('AI overviews summarize', $overview['text']);
        self::assertCount(2, $overview['references']);
        self::assertTrue($result->features->isDomainInAiOverview('example.com'));
    }

    public function testLazyAiOverviewIsResolvedByDefault(): void
    {
        $client = new MockHttpClient([
            new Response(200, [], Stream::fromString($this->fixture('serpapi/google-ai-overview-lazy.json'))),
            new Response(200, [], Stream::fromString($this->fixture('serpapi/google-ai-overview-resolved.json'))),
        ]);
        $provider = new SerpApiProvider('test-key', $client);

        $result = $provider->search('lazy ai overview', Engine::Google);
        $overview = $result->features->getAiOverview();

        self::assertStringContainsString('Resolved AI overview content', $overview['text']);
        self::assertCount(2, $client->requests());
        self::assertStringContainsString('engine=google_ai_overview', (string) $client->requests()[1]->getUri());
        self::assertStringContainsString('page_token=lazy-token-123', (string) $client->requests()[1]->getUri());
    }

    public function testLazyAiOverviewCanBeLeftUnresolved(): void
    {
        $client = new MockHttpClient([
            new Response(200, [], Stream::fromString($this->fixture('serpapi/google-ai-overview-lazy.json'))),
        ]);
        $provider = new SerpApiProvider('test-key', $client);

        $result = $provider->search('lazy ai overview', Engine::Google, ['resolve_ai_overview' => false]);
        $overview = $result->features->getAiOverview();

        self::assertSame('', $overview['text']);
        self::assertSame('lazy-token-123', $overview['page_token']);
        self::assertCount(1, $client->requests());
    }

    public function testYoutubeUsesSearchQueryParameter(): void
    {
        $client = new MockHttpClient([
            new Response(200, [], Stream::fromString($this->fixture('serpapi/youtube-search.json'))),
        ]);
        $provider = new SerpApiProvider('test-key', $client);

        $provider->search('observability talk', Engine::YouTube);

        $uri = (string) $client->requests()[0]->getUri();
        self::assertStringContainsString('engine=youtube', $uri);
        self::assertStringContainsString('search_query=observability+talk', $uri);
    }

    public function testAuthenticationErrorsMapToDomainException(): void
    {
        $provider = new SerpApiProvider(
            'bad-key',
            new MockHttpClient([new Response(403, [], Stream::fromString('{}'))])
        );
        self::expectException(AuthenticationException::class);

        $provider->search('test', Engine::Google);
    }

    public function testRateLimitErrorsExposeRetryAfter(): void
    {
        $provider = new SerpApiProvider(
            'test-key',
            new MockHttpClient([new Response(429, ['Retry-After' => ['30']], Stream::fromString('{}'))])
        );

        try {
            $provider->search('test', Engine::Google);
            self::fail('Expected rate limit exception.');
        } catch (RateLimitException $exception) {
            self::assertSame(30, $exception->retryAfterSeconds);
        }
    }

    private function fixture(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__) . '/fixtures/' . $path);
    }
}
