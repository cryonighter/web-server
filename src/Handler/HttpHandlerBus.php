<?php

namespace Handler;

use DTO\Config\HandlerConfig\FileHandlerConfig;
use DTO\Config\HandlerConfig\ForwardHandlerConfig;
use DTO\Config\HandlerConfig\HandlerConfigInterface;
use DTO\Config\HandlerConfig\RedirectHandlerConfig;
use DTO\Config\HostConfig;
use DTO\HttpRequest;
use DTO\HttpResponse;
use Exception\HttpException;
use Router\HttpRouter;

readonly class HttpHandlerBus
{
    public function __construct(
        private HttpRouter $router,
        private array $handlers,
        private array $hostConfigs,
        private HostConfig $defaultHostConfig,
    ) {
    }

    /**
     * @throws HttpException
     */
    public function handle(HttpRequest $request): HttpResponse
    {
        $hostConfig = $this->router->getConfig($request, $this->hostConfigs, $this->defaultHostConfig);
        $handlerConfig = $hostConfig->handler;

        if ($handlerConfig instanceof FileHandlerConfig) {
            /** @var FileHttpHandler $handler */
            $handler = $this->handlers[HandlerConfigInterface::TYPE_FILE] ?? throw new HttpException(500);

            return $handler->handle($request, $hostConfig->webroot, $hostConfig->indexFiles);
        }

        if ($handlerConfig instanceof ForwardHandlerConfig) {
            /** @var ForwardHttpHandler $handler */
            $handler = $this->handlers[HandlerConfigInterface::TYPE_FORWARD] ?? throw new HttpException(500);

            return $handler->handle($request, $handlerConfig->to);
        }

        if ($handlerConfig instanceof RedirectHandlerConfig) {
            /** @var RedirectHttpHandler $handler */
            $handler = $this->handlers[HandlerConfigInterface::TYPE_REDIRECT] ?? throw new HttpException(500);

            return $handler->handle($request, $handlerConfig->to, $handlerConfig->code);
        }

        throw new HttpException(500);
    }
}
