<?php

declare(strict_types=1);

namespace MagnaCapax\Serp\Enum;

enum Engine: string
{
    case Google = 'google';
    case Bing = 'bing';
    case DuckDuckGo = 'duckduckgo';
    case YouTube = 'youtube';
    case Yahoo = 'yahoo';
}
