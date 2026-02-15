<?php

namespace Handler;

use DTO\Config\HandlerConfig\CgiHandlerConfig;
use DTO\Config\HandlerConfig\FileHandlerConfig;
use DTO\Config\HandlerConfig\ForwardHandlerConfig;
use DTO\Config\HandlerConfig\HandlerConfigInterface;
use DTO\Config\HandlerConfig\RedirectHandlerConfig;
use DTO\Config\HostConfig;
use DTO\HttpContext;
use DTO\HttpRequest;
use DTO\HttpResponse;
use Exception\HttpException;
use Router\HttpRouter;

readonly class HttpHandlerBus
{
    public function __construct(
        private HttpRouter $router,
        private array $handlers,
    ) {
    }

    /**
     * @throws HttpException
     */
    public function handle(HttpRequest $request, HttpContext $context, HostConfig $rootHostConfig): HttpResponse
    {
        $hostConfig = $this->router->getRouteConfig($request, $context, $rootHostConfig);
        $handlerConfig = $hostConfig->handler;

        if ($handlerConfig instanceof CgiHandlerConfig) {
            /** @var CgiHttpHandler $handler */
            $handler = $this->handlers[HandlerConfigInterface::TYPE_CGI] ?? throw new HttpException(500);

            return $handler->handle($request, $context, $hostConfig->webroot, $handlerConfig->executable);
        }

        if ($handlerConfig instanceof FileHandlerConfig) {
            /** @var FileHttpHandler $handler */
            $handler = $this->handlers[HandlerConfigInterface::TYPE_FILE] ?? throw new HttpException(500); // TODO !!!

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

        throw HttpException::createFromCode(500);
    }
}
