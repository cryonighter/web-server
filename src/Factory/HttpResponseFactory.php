<?php

namespace Factory;

use DTO\HttpResponse;
use DTO\StreamBody\StreamBodyInterface;
use Exception\HttpException;
use Parser\HttpParser;
use Throwable;

readonly class HttpResponseFactory
{
    public function __construct(private HttpParser $parser)
    {
    }

    public function create(
        string $protocol,
        int $code,
        string $message,
        array $headers,
        StreamBodyInterface|string $body = '',
    ): HttpResponse {
        return new HttpResponse($protocol, $code, $message, $headers, $body);
    }

    public function createFromContent(string $content): HttpResponse
    {
        [$firstHeader, $otherHeaders, $body] = $this->parser->parseContent($content);

        if ($body && empty($otherHeaders['Content-Length'])) {
            $otherHeaders['Content-Length'] = [strlen($body)];
        }

        return $this->create(
            $firstHeader[0],
            $firstHeader[1],
            $firstHeader[2],
            $otherHeaders,
            $body,
        );
    }

    public function createFromException(Throwable $exception, int $code = 500): HttpResponse
    {
        $headers = ['Content-Type' => ['text/plain']];
        $protocol = 'HTTP/1.1';

        if ($exception instanceof HttpException) {
            return $this->create($protocol, $exception->getCode(), $exception->getMessage(), $headers);
        }

        return $this->create($protocol, $code,  HttpException::CODES[$code], $headers);
    }
}
