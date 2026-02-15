<?php

namespace Handler;

use DTO\HttpContext;
use DTO\HttpRequest;
use DTO\HttpResponse;
use DTO\StreamBody\CgiProcessBody;
use Exception\HttpHandlerException;
use Exception\TimeoutException;
use Factory\CgiEnvFactory;
use Factory\HttpResponseFactory;
use Parser\HttpParser;
use Throwable;

readonly class CgiHttpHandler
{
    private const int TIMEOUT = 60;

    public function __construct(
        private HttpResponseFactory $httpResponseFactory,
        private HttpParser $httpParser,
        private CgiEnvFactory $cgiEnvFactory,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function handle(
        HttpRequest $request,
        HttpContext $context,
        string $webroot,
        string $executable,
    ): HttpResponse {
        $env = $this->cgiEnvFactory->create($request, $context, $webroot, $executable);

        [$headers, $body] = $this->startCgiProcess($executable, $env, $request->body);

        [$statusCode, $statusMessage] = $this->parseStatusHeader($headers['Status'][0] ?? null);

        return $this->httpResponseFactory->create(
            $request->protocol,
            $statusCode,
            $statusMessage,
            $headers,
            $body,
        );
    }

    private function startCgiProcess(string $executable, array $env, string $stdin): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $process = proc_open($executable, $descriptors, $pipes, null, $env);

        if (!is_resource($process)) {
            throw new HttpHandlerException("Failed to start CGI process: $executable");
        }

        try {
            if ($stdin) {
                fwrite($pipes[0], $stdin);
            }
            fclose($pipes[0]);
            unset($pipes[0]);

            $headers = $this->httpParser->parseHeaders($this->readHeadersFromSocket($pipes[1]));
            $body = new CgiProcessBody($process, $pipes[1], $pipes[2]);

            return [$headers, $body];
        } catch (Throwable $exception) {
            stream_set_blocking($pipes[2], false);
            $errors = stream_get_contents($pipes[2]);

            foreach ($pipes as $pipe) {
                fclose($pipe);
            }

            $exitCode = proc_close($process);

            if ($exitCode !== 0) {
                throw new HttpHandlerException(
                    message: "CGI script failed with exit code: $exitCode" . ($errors ? "\n$errors" : ''),
                    previous: $exception,
                );
            }

            throw $exception;
        }
    }

    private function readHeadersFromSocket($stream): array
    {
        $headers = [];

        while (!feof($stream)) {
            $read = [$stream];
            $write = null;
            $except = null;
            $result = stream_select($read, $write, $except, self::TIMEOUT);

            if (!$result) {
                if ($result === false) {
                    throw new HttpHandlerException('Error while waiting for CGI response');
                }

                if ($result === 0) {
                    throw new TimeoutException('CGI script timeout: no response within ' . self::TIMEOUT . ' seconds');
                }
            }

            $header = fgets($stream, 8192);

            if (in_array($header, ["\r\n", "\n"])) {
                break;
            }

            $headers[] = $header;
        }

        if (empty($headers)) {
            throw new HttpHandlerException('Empty CGI response');
        }

        return $headers;
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
