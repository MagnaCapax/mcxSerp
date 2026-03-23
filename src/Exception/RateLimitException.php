<?php

declare(strict_types=1);

namespace MagnaCapax\Serp\Exception;

final class RateLimitException extends SerpException
{
    public function __construct(
        string $message = 'Rate limit exceeded.',
        public readonly ?int $retryAfterSeconds = null,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
