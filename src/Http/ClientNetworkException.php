<?php

declare(strict_types=1);

namespace MagnaCapax\Serp\Http;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;

final class ClientNetworkException extends ClientRequestException implements NetworkExceptionInterface
{
    public function __construct(
        string $message,
        RequestInterface $request,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $request, $code, $previous);
    }
}
