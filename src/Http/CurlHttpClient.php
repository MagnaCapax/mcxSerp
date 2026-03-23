<?php

declare(strict_types=1);

namespace MagnaCapax\Serp\Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class CurlHttpClient implements ClientInterface
{
    public function __construct(
        private readonly int $timeout = 30,
    ) {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $handle = curl_init((string) $request->getUri());

        if ($handle === false) {
            throw new ClientNetworkException('Unable to initialize cURL.', $request);
        }

        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $headers[] = $name . ': ' . $value;
            }
        }

        $responseHeaders = [];

        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $request->getMethod(),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$responseHeaders): int {
                $trimmed = trim($header);
                if ($trimmed === '' || !str_contains($trimmed, ':')) {
                    return strlen($header);
                }

                [$name, $value] = explode(':', $trimmed, 2);
                $responseHeaders[trim($name)][] = trim($value);

                return strlen($header);
            },
        ]);

        $body = (string) $request->getBody();
        if ($body !== '' && in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($handle);

        if ($responseBody === false) {
            $message = curl_error($handle);
            $code = curl_errno($handle);
            curl_close($handle);

            throw new ClientNetworkException($message !== '' ? $message : 'Network request failed.', $request, $code);
        }

        $statusCode = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        return new Response(
            $statusCode,
            $responseHeaders,
            Stream::fromString($responseBody),
        );
    }
}
