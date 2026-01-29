<?php

use DTO\Config\GlobalConfig;
use Exception\HttpException;
use Factory\HttpRequestFactory;
use Factory\HttpResponseFactory;
use Handler\HttpHandlerBus;
use Logger\LoggerInterface;
use Parser\ConfigParser;

class WebServer
{
    private const int CRYPTO_METHOD = STREAM_CRYPTO_METHOD_TLSv1_2_SERVER | STREAM_CRYPTO_METHOD_TLSv1_3_SERVER;

    protected readonly WebServerEvents $events;

    protected int $processedRequests = 0;
    protected bool $shutdown = false;

    public function __construct(
        protected readonly LoggerInterface $logger,
        private readonly ConfigParser $configParser,
        private readonly HttpHandlerBus $httpHandlerBus,
        private readonly HttpRequestFactory $httpRequestFactory,
        private readonly HttpResponseFactory $httpResponseFactory,
    ) {
        $this->events = new WebServerEvents();
    }

    public function start(): void
    {
        $config = $this->configParser->create();

        $host = '0.0.0.0';
        $sockets = [];

        foreach ($config->hosts as $port => $hostConfig) {
            $context = [
                'socket' => [
                    'backlog' => 256,
                    'so_reuseport' => 1,
                ],
            ];

            if ($hostConfig->tls) {
                $context['ssl'] = [
                    'local_cert' => $hostConfig->tls->certificate,
                    'local_pk' => $hostConfig->tls->privateKey,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                    'disable_compression' => true,
                    'SNI_enabled' => true,
                    'crypto_method' => self::CRYPTO_METHOD,
                ];
            }

            $socket = @stream_socket_server(
                "tcp://$host:$port",
                $errMsg,
                $errorCode,
                STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
                stream_context_create($context),
            );

            if (!$socket) {
                throw new RuntimeException("Failed to create socket: $errMsg ($errorCode)");
            }

            $sockets[] = $socket;

            $protocol = $hostConfig->tls ? 'https' : 'http';

            $this->logger->info("Server running on $protocol://$host:$port");
        }

        $this->run($sockets, $config);
    }

    protected function run(array $sockets, GlobalConfig $config): void
    {
        $this->logger->info('Process started with PID: ' . getmypid());

        pcntl_async_signals(true);

        pcntl_signal(SIGTERM, fn() => $this->handleShutdown());
        pcntl_signal(SIGINT, fn() => $this->handleShutdown());
        pcntl_signal(SIGQUIT, fn() => $this->handleShutdown());

        $this->listen($sockets, $config);
    }

    protected function listen(array $sockets, GlobalConfig $config): void
    {
        $this->events->onListen();

        while (!$this->shutdown && $this->events->onListenLoop()) {
            $read = $sockets;
            $write = null;
            $except = null;

            $selectResult = @stream_select($read, $write, $except, 1);

            if ($selectResult === false) {
                break;
            }

            if ($selectResult === 0) {
                continue;
            }

            foreach ($read as $readSocket) {
                $connection = @stream_socket_accept($readSocket, 0);

                if (!$connection) {
                    continue;
                }

                $this->processedRequests++;

                $socketAddress = stream_socket_get_name($readSocket, false);
                $port = explode(':', $socketAddress)[1];

                $hostConfig = $config->hosts[$port];

                $isTLS = (bool) $hostConfig->tls;
                $protocol = $isTLS ? 'HTTPS' : 'HTTP';

                $remoteAddress = stream_socket_get_name($connection, true);

                $this->logger->info("New $protocol connection accepted from $remoteAddress");

                try {
                    if ($isTLS) {
                        stream_set_blocking($connection, true);

                        if (!@stream_socket_enable_crypto($connection, true, self::CRYPTO_METHOD)) {
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

                        if ($contentSize > $config->requestSizeMax) {
                            throw HttpException::createFromCode(413);
                        }
                    } while ($chunkSize == 8192);

                    $request = $this->httpRequestFactory->createFromContent($content);

                    $this->logger->info("Request received: $request->startLine");

                    $response = $this->httpHandlerBus->handle($request, $hostConfig);

                    foreach ($response->read(1048576) as $chunk) {
                        fwrite($connection, $chunk);
                    }

                    $this->logger->info("Response sent: $response->protocol $response->code $response->message");
                } catch (Throwable $exception) {
                    if (!$exception instanceof HttpException || !$exception->is4xx()) {
                        $this->logger->error($exception);
                    }

                    $response = $this->httpResponseFactory->createFromException($exception);

                    $this->logger->info("Response sent: $response->protocol $response->code $response->message");

                    fwrite($connection, $response);
                } finally {
                    $this->logger->debug("Requests processed: $this->processedRequests");

                    fclose($connection);

                    $this->events->onListenLoopFinally();
                }
            }
        }

        $this->events->onListenEnd();
    }

    protected function handleShutdown(): void
    {
        $this->logger->info('Received shutdown signal, stopping server...');
        $this->shutdown = true;
    }
}
