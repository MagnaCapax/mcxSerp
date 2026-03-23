<?php

declare(strict_types=1);

namespace MagnaCapax\Serp\Tests\Model;

use MagnaCapax\Serp\Model\SerpFeatures;
use PHPUnit\Framework\TestCase;

final class SerpFeaturesTest extends TestCase
{
    public function testTypedAccessorsAndDomainChecks(): void
    {
        $features = new SerpFeatures([
            'ai_overview' => [
                'text' => 'Summary',
                'references' => [
                    ['title' => 'Ref', 'link' => 'https://example.com/source'],
                ],
            ],
            'featured_snippet' => [
                'title' => 'Snippet title',
                'link' => 'https://snippet.example.com/post',
                'snippet' => 'Snippet body',
                'domain' => 'snippet.example.com',
            ],
            'people_also_ask' => [
                ['question' => 'What is this?', 'snippet' => 'Answer', 'link' => 'https://example.com/q'],
            ],
        ]);

        self::assertTrue($features->has('ai_overview'));
        self::assertSame('Summary', $features->getAiOverview()['text']);
        self::assertCount(1, $features->getPeopleAlsoAsk());
        self::assertTrue($features->isDomainInAiOverview('example.com'));
        self::assertTrue($features->isDomainInFeaturedSnippet('snippet.example.com'));
        self::assertFalse($features->has('ads'));
    }
}
