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
$hostConfigParser = new HostConfigParser();

$httpRequestFactory = new HttpRequestFactory($httpParser);
$httpResponseFactory = new HttpResponseFactory($httpParser);

$httpHandlerBus = new HttpHandlerBusFactory()->create(
    $httpResponseFactory,
    $httpParser,
    new HttpRouter($logger),
    $logger,
);

$cryptoMethod = STREAM_CRYPTO_METHOD_TLSv1_2_SERVER | STREAM_CRYPTO_METHOD_TLSv1_3_SERVER;

$host = '0.0.0.0';
$sockets = [];

$hostConfigs = $hostConfigParser->createAll();

foreach ($hostConfigs as $port => $config) {
    $socket = stream_socket_server(
        "tcp://$host:$port",
        $errMsg,
        $errorCode,
        STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
        $config->tls ? stream_context_create([
            'ssl' => [
                'local_cert' => $config->tls->certificate,
                'local_pk' => $config->tls->privateKey,
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
                'disable_compression' => true,
                'SNI_enabled' => true,
                'crypto_method' => $cryptoMethod,
            ],
        ]) : null,
    );

    if (!$socket) {
        throw new RuntimeException("Failed to create socket: $errMsg ($errorCode)");
    }

    $sockets[] = $socket;

    $protocol = $config->tls ? 'https' : 'http';

    $logger->info("Server running on $protocol://$host:$port");
}

while (true) {
    $read = $sockets;
    $write = null;
    $except = null;

    if (stream_select($read, $write, $except, null) === false) {
        break;
    }

    foreach ($read as $readSocket) {
        $connection = stream_socket_accept($readSocket, -1);

        if (!$connection) {
            continue;
        }

        $socketAddress = stream_socket_get_name($readSocket, false);
        $port = explode(':', $socketAddress)[1];

        $isTLS = (bool) $hostConfigs[$port]->tls;
        $protocol = $isTLS ? 'HTTPS' : 'HTTP';

        $remoteAddress = stream_socket_get_name($connection, true);

        $logger->info("New $protocol connection accepted from $remoteAddress");

        try {
            if ($isTLS) {
                stream_set_blocking($connection, true);

                if (!@stream_socket_enable_crypto($connection, true, $cryptoMethod)) {
                    throw new RuntimeException('Failed to enable SSL/TLS encryption');
                }
            }

            $content = '';
            $contentSize = 0;

            do {
                $chunk = fread($connection, 8192);
                $chunkSize = strlen($chunk);

                if ($content === '' && preg_match('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/', substr($chunk, 0, 20))) {
                    throw new RuntimeException('This is not the HTTP protocol');
                }

                $contentSize += $chunkSize;
                $content .= $chunk;

                if ($contentSize > $maxRequestSize) {
                    throw HttpException::createFromCode(413);
                }
            } while ($chunkSize == 8192);

            $request = $httpRequestFactory->createFromContent($content);

            $logger->info("Request received: $request->startLine");

            $response = $httpHandlerBus->handle($request, $hostConfigs[$port]);

            foreach ($response->read(1048576) as $chunk) {
                fwrite($connection, $chunk);
            }

            $logger->info("Response sent: $response->protocol $response->code $response->message");
        } catch (Throwable $exception) {
            if (!$exception instanceof HttpException || !$exception->is4xx()) {
                $logger->error($exception);
            }

            $response = $httpResponseFactory->createFromException($exception);

            $logger->info("Response sent: $response->protocol $response->code $response->message");

            fwrite($connection, $response);
        } finally {
            fclose($connection);
        }
    }
}
