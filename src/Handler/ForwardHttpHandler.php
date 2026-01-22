<?php

namespace Handler;

use DTO\HttpRequest;
use DTO\HttpResponse;
use DTO\StreamBody;
use Exception\HttpException;
use Factory\HttpResponseFactory;
use Logger\LoggerInterface;
use Parser\HttpParser;

readonly class ForwardHttpHandler
{
    public function __construct(
        private HttpResponseFactory $httpResponseFactory,
        private HttpParser $parser,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws HttpException
     */
    public function handle(HttpRequest $request, string $to): HttpResponse
    {
        $url = parse_url($to);

        $scheme = $url['scheme'] ?? 'http';
        $host = $url['host'] ?? implode(':', $request->getHost())[0];
        $port = $url['port'] ?? ($scheme === 'https' ? 443 : 80);
        $path = $url['path'] ?? '/';
        $query = [];
        parse_str($url['query'] ?? '', $query);

        if (str_ends_with($path, '/')) {
            $path .= substr($request->getPathWithoutQuery(), 1);
        } else {
            $path = $request->getPathWithoutQuery();
        }

        $mergedQuery = http_build_query(array_merge($request->getQuery(), $query));

        if ($mergedQuery) {
            $path .= '?' . $mergedQuery;
        }

        $transport = match ($scheme) {
            'http' => 'tcp',
            'https' => 'tls',
            default => $scheme,
        };

        $address = "$transport://$host:$port";

        $socket = stream_socket_client(
            $address,
            $errorCode,
            $errorMessage,
            30,
            STREAM_CLIENT_CONNECT,
            stream_context_create(['ssl' => ['verify_peer' => false]]),
        );

        if (!$socket) {
            $this->logger->error("Failed to connect to $address. Error code: $errorCode, Error message: $errorMessage");

            throw HttpException::createFromCode(502);
        }

        $this->logger->debug("Forwarding request to $address$path");

        fwrite($socket, $this->prepareForwardingRequest($request, $host, $path));

        [$firstHeader, $otherHeaders] = $this->parser->parse($this->readHeadersFromSocket($socket));

        return $this->httpResponseFactory->create(
            $firstHeader[0],
            $firstHeader[1],
            $firstHeader[2],
            $otherHeaders,
            new StreamBody($socket),
        );
    }

    private function prepareForwardingRequest(HttpRequest $request, string $host, string $path): string
    {
        $headers = [];

        foreach ($request->headers as $key => $values) {
            if ($key === 'Host') {
                continue;
            }
            foreach ($values as $value) {
                $headers[] = "$key: $value";
            }
        }

        $headers[] = "Host: $host";
        $headers[] = "Connection: close";

        return "$request->method $path $request->protocol\r\n" . implode("\r\n", $headers) . "\r\n\r\n$request->body";
    }

    /**
     * @param resource $socket
     *
     * @return string
     */
    private function readHeadersFromSocket($socket): string
    {
        $headers = '';

        while (!feof($socket)) {
            $row = fgets($socket);

            $headers .= $row;

            if (in_array($row, ["\r\n", "\n"])) {
                break;
            }
        }

        return $headers;
    }
}
