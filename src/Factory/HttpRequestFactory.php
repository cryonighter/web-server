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
                throw HttpException::createFromCode(431);
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

        $transferEncodings = array_map(
            fn(string $encoding): string => strtolower(trim($encoding)),
            explode(',', $headers['Transfer-Encoding'][0] ?? ''),
        );

        if (in_array('gzip', $transferEncodings)) {
            throw HttpException::createFromCode(501);
        }

        if (in_array('chunked', $transferEncodings)) {
            if ($protocol !== 'HTTP/1.1') {
                throw HttpException::createFromCode(501);
            }

            $content = $this->readContentChunked($connection, $requestSizeMax - $requestSize);
        } else {
            $contentLength = $headers['Content-Length'][0] ?? 0;

            if ($requestSize + $contentLength > $requestSizeMax) {
                throw HttpException::createFromCode(413);
            }

            $content = $this->readContentByLength($connection, $contentLength);
        }

        return $this->create($method, $path, $protocol, $headers, $content);
    }

    private function readContentByLength($connection, int $size): string
    {
        $content = '';
        $remaining = $size;

        while ($remaining != 0) {
            $chunk = fread($connection, min($remaining, 8192));

            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('Failed to read content');
            }

            $content .= $chunk;
            $remaining -= strlen($chunk);
        }

        return $content;
    }

    /**
     * @throws HttpException
     */
    private function readContentChunked($connection, int $maxSize): string
    {
        $content = '';
        $totalSize = 0;

        while (true) {
            $chunkSizeLine = fgets($connection, 8192);
            $chunkSize = hexdec(trim(explode(';', $chunkSizeLine, 2)[0]));

            if ($chunkSize < 0) {
                throw new RuntimeException('Invalid chunk size');
            }

            // Финальный чанк - тот, что имеет размер 0 (хотя на самом деле там дальше trailer headers)
            if ($chunkSize === 0) {
                do {
                    // Trailer - опциональные HTTP заголовки после финального чанка
                    $trailer = fgets($connection, 8192);
                } while (!in_array($trailer, ["\r\n", "\n"]));

                break;
            }

            if ($totalSize + $chunkSize > $maxSize) {
                throw HttpException::createFromCode(413);
            }

            $content .= $this->readContentByLength($connection, $chunkSize);

            $totalSize += $chunkSize;

            $crlf = fread($connection, 2);
            if ($crlf != "\r\n") {
                throw new RuntimeException('Missing CRLF after chunk data');
            }
        }

        return $content;
    }
}
