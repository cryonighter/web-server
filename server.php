<?php

use Exception\HttpException;
use Factory\HttpRequestFactory;
use Factory\HttpResponseFactory;
use Handler\FileHttpHandler;
use Parser\HttpParser;

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

echo "Server running on http://$host:$port\n";

$webroot = './webroot';
$maxRequestSize = 1048576 * 16; // 16Mb

$httpParser = new HttpParser();
$httpRequestFactory = new HttpRequestFactory($httpParser);
$httpResponseFactory = new HttpResponseFactory($httpParser);

$fileHttpHandler = new FileHttpHandler($httpResponseFactory);

while ($connection = stream_socket_accept($socket, -1)) {
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

        $request = $httpRequestFactory->createFromContent($content);

        $response = $fileHttpHandler->handle($request, $webroot);

        foreach ($response->read(1048576) as $chunk) {
            fwrite($connection, $chunk);
        }
    } catch (Throwable $exception) {
        fwrite($connection, $httpResponseFactory->createFromException($exception));
    } finally {
        fclose($connection);
    }
}
