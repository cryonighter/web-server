<?php

use Exception\HttpException;
use Factory\HttpHandlerBusFactory;
use Factory\HttpRequestFactory;
use Factory\HttpResponseFactory;
use Logger\LogLevel;
use Logger\StdoutLogger;
use Parser\HostConfigParser;
use Parser\HttpParser;
use Router\HttpRouter;

if (PHP_SAPI !== 'cli') {
    throw new RuntimeException('This script can be run only from command line');
}

spl_autoload_register(function(string $class): void {
    $file = __DIR__ . '/src/' . str_replace('\\', '/', $class) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

$host = '0.0.0.0';
$port = 8080;

$socket = stream_socket_server( "tcp://$host:$port", $errorCode, $errMsg);

if (!$socket) {
    throw new RuntimeException("Failed to create socket: $errMsg ($errorCode)");
}

$maxRequestSize = 1048576 * 16; // 16Mb

$logger = new StdoutLogger(
    match (strtoupper(getopt('', ['log-level::'])['log-level'] ?? 'INFO')) {
        'FATAL' => LogLevel::FATAL,
        'ERROR' => LogLevel::ERROR,
        'WARNING' => LogLevel::WARNING,
        'NOTICE' => LogLevel::NOTICE,
        'DEBUG' => LogLevel::DEBUG,
        'TRACE' => LogLevel::TRACE,
        default => LogLevel::INFO,
    },
);

$httpParser = new HttpParser();

$httpRequestFactory = new HttpRequestFactory($httpParser);
$httpResponseFactory = new HttpResponseFactory($httpParser);

$httpHandlerBus = new HttpHandlerBusFactory()->create(
    $httpResponseFactory,
    new HostConfigParser(),
    $httpParser,
    new HttpRouter($logger),
    $logger,
);

$logger->info("Server running on http://$host:$port");

while ($connection = stream_socket_accept($socket, -1)) {
    $remoteAddress = stream_socket_get_name($connection, false);

    $logger->info("New connection accepted from $remoteAddress");

    $content = '';
    $contentSize = 0;

    try {
        do {
            $chunk = fread($connection, 8192);
            $chunkSize = strlen($chunk);

            $contentSize += $chunkSize;
            $content .= $chunk;

            if ($contentSize > $maxRequestSize) {
                throw HttpException::createFromCode(413);
            }
        } while ($chunkSize == 8192);

        if ($content && ord($content[0]) > 127) {
            throw new RuntimeException('Non-HTTP request received (possibly TLS/SSL handshake)');
        }

        $request = $httpRequestFactory->createFromContent($content);

        $logger->info("Request received: $request->startLine");

        $response = $httpHandlerBus->handle($request);

        foreach ($response->read(1048576) as $chunk) {
            fwrite($connection, $chunk);
        }

        $logger->info("Response sent: $response->protocol $response->code $response->message");
    } catch (Throwable $exception) {
        if ($exception instanceof HttpException && $exception->is4xx()) {
            $logger->info("Response sent: HTTP/1.1 {$exception->getCode()} {$exception->getMessage()}");
        } else {
            $logger->error($exception);
        }

        fwrite($connection, $httpResponseFactory->createFromException($exception));
    } finally {
        fclose($connection);
    }
}
