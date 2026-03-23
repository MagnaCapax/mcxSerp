<?php

declare(strict_types=1);

namespace MagnaCapax\Serp\Provider;

use MagnaCapax\Serp\Enum\Engine;
use MagnaCapax\Serp\Model\SearchResult;

interface ProviderInterface
{
    public function getName(): string;

    public function getSupportedEngines(): array;

    public function search(string $query, Engine $engine = Engine::Google, array $options = []): SearchResult;
}
