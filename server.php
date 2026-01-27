<?php

use Factory\HttpHandlerBusFactory;
use Factory\HttpRequestFactory;
use Factory\HttpResponseFactory;
use Factory\SharedMemoryIpcFactory;
use Factory\SocketPairIpcFactory;
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
        'INFO' => LogLevel::INFO,
        'DEBUG' => LogLevel::DEBUG,
        'TRACE' => LogLevel::TRACE,
        default => throw new RuntimeException('Unknown log level'),
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

$model = strtolower(getopt('', ['model::'])['model'] ?? 'single');

if (!in_array($model, ['single', 'prefork'])) {
    throw new RuntimeException("Unknown server model '$model'");
}

$logger->debug("Selected server model: $model");

if ($model === 'prefork') {
    if (!extension_loaded('pcntl')) {
        throw new RuntimeException("PCNTL extension is required for prefork model");
    }

    $ipc = strtolower(getopt('', ['ipc::'])['ipc'] ?? '') ?: (extension_loaded('shmop') ? 'shared_memory' : 'socket_pair');

    $logger->debug("Selected IPC type: $ipc");

    $ipcFactory = match ($ipc) {
        'shared_memory' => new SharedMemoryIpcFactory(),
        'socket_pair' => new SocketPairIpcFactory(),
        default => throw new RuntimeException("Unknown IPC type '$ipc'"),
    };
} else {
    $ipcFactory = null;
}

$server = match ($model) {
    'single' => new WebServer($logger, $hostConfigParser, $httpHandlerBus, $httpRequestFactory, $httpResponseFactory),
    'prefork' => new PreForkWebServer($logger, $hostConfigParser, $httpHandlerBus, $httpRequestFactory, $httpResponseFactory, $ipcFactory),
    default => throw new RuntimeException("Unknown server model '$model'"),
};

$server->start();
