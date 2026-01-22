<?php

namespace Factory;

use DTO\HttpRequest;
use Parser\HttpParser;

readonly class HttpRequestFactory
{
    public function __construct(private HttpParser $parser)
    {
    }

    public function create(
        string $method,
        string $path,
        string $protocol,
        array $headers,
        string $body = '',
        string $source = '',
    ): HttpRequest {
        return new HttpRequest($method, $path, $protocol, $headers, $body, $source);
    }

    public function createFromContent(string $content): HttpRequest
    {
        [$firstHeader, $otherHeaders, $body] = $this->parser->parse($content);

        return $this->create(
            $firstHeader[0],
            $firstHeader[1],
            $firstHeader[2],
            $otherHeaders,
            $body,
            $content,
        );
    }
}
