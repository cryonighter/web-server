<?php

namespace Factory;

use DTO\HttpRequest;
use Exception\HttpException;
use Parser\HttpParser;
use RuntimeException;

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

    /**
     * @throws HttpException
     */
    public function createFromStream($connection, int $requestSizeMax): HttpRequest
    {
        $length = 8192;

        $startLine = fgets($connection, $length);
        $requestSize = strlen($startLine);

        if (preg_match('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/', substr($startLine, 0, 20))) {
            throw new RuntimeException('This is not the HTTP protocol');
        }

        if ($requestSize == ($length - 1)) {
            throw HttpException::createFromCode(414);
        }

        [$method, $path, $protocol] = $this->parser->parseStartLine($startLine);

        $headers = [];

        do {
            $header = fgets($connection, $length);
            $headerSize = strlen($header);

            if ($headerSize == ($length - 1)) {
                throw HttpException::createFromCode(400);
            }

            $requestSize += $headerSize;

            if ($requestSize > $requestSizeMax) {
                throw HttpException::createFromCode(413);
            }

            if (in_array($header, ["\r\n", "\n"])) {
                break;
            }

            [$name, $value] = $this->parser->parseHeaderLine($header);

            if (!isset($headers[$name])) {
                $headers[$name] = [];
            }

            $headers[$name][] = $value;
        } while (true);

        $contentLength = $headers['Content-Length'][0] ?? 0;

        if ($requestSize + $contentLength > $requestSizeMax) {
            throw HttpException::createFromCode(413);
        }

        $content = '';
        $contentSize = 0;

        while ($contentLength != $contentSize) {
            $chunk = fread($connection, $length);
            $chunkSize = strlen($chunk);

            $content .= $chunk;
            $contentSize += $chunkSize;
        }

        return $this->create($method, $path, $protocol, $headers, $content);
    }
}
