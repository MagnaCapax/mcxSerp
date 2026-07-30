<?php

declare(strict_types=1);

namespace MagnaCapax\Serp\Tests\Provider;

use MagnaCapax\Serp\Enum\Device;
use MagnaCapax\Serp\Enum\Engine;
use MagnaCapax\Serp\Exception\AuthenticationException;
use MagnaCapax\Serp\Exception\ProviderException;
use MagnaCapax\Serp\Exception\RateLimitException;
use MagnaCapax\Serp\Exception\UnsupportedEngineException;
use MagnaCapax\Serp\Http\Response;
use MagnaCapax\Serp\Http\Stream;
use MagnaCapax\Serp\Provider\SerperProvider;
use MagnaCapax\Serp\Tests\Http\MockHttpClient;
use PHPUnit\Framework\TestCase;

final class SerperProviderTest extends TestCase
{
    public function testSearchNormalizesOrganicResultsAndFeatures(): void
    {
        $provider = new SerperProvider(
            'test-key',
            new MockHttpClient([
                new Response(200, ['Content-Type' => ['application/json']], Stream::fromString($this->fixture('serper/search-with-features.json'))),
            ])
        );

        $result = $provider->search('llm observability', Engine::Google, [
            'device' => Device::Desktop,
            'country' => 'us',
            'language' => 'en',
            'numResults' => 10,
        ]);

        self::assertSame('serper', $result->provider);
        self::assertCount(1, $result->organicResults);
        self::assertSame(1, $result->organicResults[0]->position);
        self::assertSame('https://example.com/llm-observability', $result->organicResults[0]->url);
        self::assertTrue($result->features->has('featured_snippet'));
        self::assertTrue($result->features->has('people_also_ask'));
        self::assertSame('Observability', $result->features->getKnowledgeGraph()['title']);
    }

    public function testSearchSendsExpectedJsonPayload(): void
    {
        $client = new MockHttpClient([
            new Response(200, ['Content-Type' => ['application/json']], Stream::fromString($this->fixture('serper/search-success.json'))),
        ]);
        $provider = new SerperProvider('test-key', $client);

        $provider->search('site:example.com observability', Engine::Google, [
            'device' => Device::Mobile,
            'country' => 'us',
            'language' => 'en',
            'page' => 2,
            'numResults' => 20,
            'autocorrect' => false,
        ]);

        $request = $client->requests()[0];
        self::assertSame('POST', $request->getMethod());
        self::assertStringContainsString('/search', (string) $request->getUri());
        self::assertSame('test-key', $request->getHeaderLine('X-API-KEY'));

        $payload = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('mobile', $payload['device']);
        self::assertSame(20, $payload['num']);
        self::assertSame(2, $payload['page']);
        self::assertFalse($payload['autocorrect']);
    }

    public function testSearchRejectsUnsupportedEngine(): void
    {
        $provider = new SerperProvider('test-key', new MockHttpClient());
        self::expectException(UnsupportedEngineException::class);

        $provider->search('test', Engine::Bing);
    }

    public function testAuthenticationErrorsMapToDomainException(): void
    {
        $provider = new SerperProvider(
            'bad-key',
            new MockHttpClient([new Response(401, [], Stream::fromString('{}'))])
        );
        self::expectException(AuthenticationException::class);

        $provider->search('test');
    }

    public function testRateLimitErrorsExposeRetryAfter(): void
    {
        $provider = new SerperProvider(
            'test-key',
            new MockHttpClient([new Response(429, ['Retry-After' => ['120']], Stream::fromString('{}'))])
        );

        try {
            $provider->search('test');
            self::fail('Expected rate limit exception.');
        } catch (RateLimitException $exception) {
            self::assertSame(120, $exception->retryAfterSeconds);
        }
    }

    /**
     * Regression: an exhausted quota answers 400 {"message":"Not enough credits"}. That used to
     * fall through to `default`, decode to zero organic results, and reach the consumer as
     * "the domain does not rank" — silently, for 16 days. It must raise instead.
     */
    public function testQuotaExhaustedResponseRaisesInsteadOfDecodingToEmptyResults(): void
    {
        $provider = new SerperProvider(
            'test-key',
            new MockHttpClient([
                new Response(400, [], Stream::fromString('{"message":"Not enough credits","statusCode":400}')),
            ])
        );

        try {
            $provider->search('test');
            self::fail('Expected provider exception for HTTP 400.');
        } catch (ProviderException $exception) {
            self::assertStringContainsString('400', $exception->getMessage());
            self::assertStringContainsString('Not enough credits', $exception->getMessage());
        }
    }

    public function testUnexpectedClientErrorRaisesEvenWithoutAMessageBody(): void
    {
        $provider = new SerperProvider(
            'test-key',
            new MockHttpClient([new Response(404, [], Stream::fromString('{}'))])
        );
        self::expectException(ProviderException::class);

        $provider->search('test');
    }

    private function fixture(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__) . '/fixtures/' . $path);
    }
}
