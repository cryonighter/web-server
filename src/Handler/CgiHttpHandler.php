<?php

namespace Handler;

use DTO\HttpContext;
use DTO\HttpRequest;
use DTO\HttpResponse;
use Exception\HttpHandlerException;
use Factory\CgiEnvFactory;
use Factory\HttpResponseFactory;
use Parser\HttpParser;

readonly class CgiHttpHandler
{
    public function __construct(
        private HttpResponseFactory $httpResponseFactory,
        private HttpParser $httpParser,
        private CgiEnvFactory $cgiEnvFactory,
    ) {
    }

    public function handle(
        HttpRequest $request,
        HttpContext $context,
        string $webroot,
        string $executable,
    ): HttpResponse {
        $env = $this->cgiEnvFactory->create($request, $context, $webroot, $executable);

        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $process = proc_open($executable, $descriptors, $pipes, null, $env);

        if (!is_resource($process)) {
            throw new HttpHandlerException('Failed to start CGI process');
        }

        try {
            if ($request->body) {
                fwrite($pipes[0], $request->body);
            }
            fclose($pipes[0]);

            $output = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            $exitCode = proc_close($process);

            if ($output) {
                return $this->createResponseFromCgiContent($output, $request->protocol);
            }

            if ($exitCode !== 0) {
                throw new HttpHandlerException(
                    message: "CGI script failed with exit code: $exitCode" . ($errors ? "\n$errors" : ''),
                );
            }

            throw new HttpHandlerException('Empty CGI response');
        } finally {
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            if (is_resource($process)) {
                proc_close($process);
            }
        }
    }

    private function createResponseFromCgiContent(string $output, string $protocol): HttpResponse
    {
        $blocks = preg_split('/\r?\n\r?\n/', $output, 2);

        $headerBlock = trim($blocks[0]);
        $body = $blocks[1] ?? '';

        $headerLines = preg_split('/\r?\n/', $headerBlock);
        $headers = $this->httpParser->parseHeaders($headerLines);

        [$statusCode, $statusMessage] = $this->parseStatusHeader($headers['Status'][0] ?? null);

        return $this->httpResponseFactory->create(
            $protocol,
            $statusCode,
            $statusMessage,
            $headers,
            $body,
        );
    }

    private function parseStatusHeader(?string $header): array
    {
        if ($header === null) {
            return [200, 'OK'];
        }

        if (preg_match('/^(\d{3})\s*(.*)$/', $header, $matches)) {
            return [$matches[1], $matches[2] ?: 'OK'];
        }

        throw new HttpHandlerException('Invalid status header format');
    }
}