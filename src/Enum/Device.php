<?php

declare(strict_types=1);

namespace MagnaCapax\Serp\Enum;

enum Device: string
{
    case Desktop = 'desktop';
    case Mobile = 'mobile';
    case Tablet = 'tablet';
}
