<?php

declare(strict_types=1);

namespace MagnaCapax\Serp\Tests\Http;

use MagnaCapax\Serp\Http\ClientNetworkException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class MockHttpClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    private array $requests = [];

    /** @var list<ResponseInterface|\Throwable> */
    private array $queue;

    public function __construct(array $queue = [])
    {
        $this->queue = array_values($queue);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        if ($this->queue === []) {
            throw new ClientNetworkException('No mock response queued.', $request);
        }

        $next = array_shift($this->queue);

        if ($next instanceof \Throwable) {
            throw $next;
        }

        return $next;
    }

    public function requests(): array
    {
        return $this->requests;
    }
}
