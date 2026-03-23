<?php

declare(strict_types=1);

namespace MagnaCapax\Serp\Tests\Model;

use MagnaCapax\Serp\Enum\Device;
use MagnaCapax\Serp\Enum\Engine;
use MagnaCapax\Serp\Model\OrganicResult;
use MagnaCapax\Serp\Model\SearchResult;
use MagnaCapax\Serp\Model\SerpFeatures;
use PHPUnit\Framework\TestCase;

final class SearchResultTest extends TestCase
{
    public function testFindPositionReturnsMatchingOrganicPosition(): void
    {
        $result = new SearchResult(
            query: 'example query',
            engine: Engine::Google,
            device: Device::Desktop,
            provider: 'serpapi',
            timestamp: new \DateTimeImmutable('2026-03-23T12:00:00+00:00'),
            organicResults: [
                new OrganicResult(1, 'First', 'https://example.com/page'),
                new OrganicResult(2, 'Second', 'https://other.example.org/page'),
            ],
            features: new SerpFeatures(['related_searches' => [['query' => 'test']]]),
            rawResponse: ['ok' => true],
        );

        self::assertSame(1, $result->findPosition('example.com'));
        self::assertNull($result->findPosition('missing.example'));
    }

    public function testJsonSerializeProducesExpectedShape(): void
    {
        $result = new SearchResult(
            query: 'example query',
            engine: Engine::Google,
            device: Device::Mobile,
            provider: 'serper',
            timestamp: new \DateTimeImmutable('2026-03-23T12:00:00+00:00'),
            organicResults: [new OrganicResult(1, 'First', 'https://example.com/page', 'Snippet')],
            features: new SerpFeatures(['featured_snippet' => ['title' => 'Title']]),
            country: 'us',
            language: 'en',
            totalResults: 100,
            page: 2,
            rawResponse: ['raw' => true],
        );

        $json = json_encode($result, JSON_THROW_ON_ERROR);
        self::assertJson($json);
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('mobile', $data['device']);
        self::assertSame('serper', $data['provider']);
        self::assertSame(2, $data['page']);
        self::assertSame('example.com', $data['organic_results'][0]['domain']);
        self::assertSame('Title', $data['features']['featured_snippet']['title']);
    }
}
