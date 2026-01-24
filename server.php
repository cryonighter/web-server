<?php

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

$server = new WebServer($hostConfigParser, $httpHandlerBus, $httpRequestFactory, $httpResponseFactory, $logger);
$server->start();
