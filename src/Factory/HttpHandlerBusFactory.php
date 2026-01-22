<?php

namespace Factory;

use DTO\Config\HandlerConfig\HandlerConfigInterface;
use Handler\FileHttpHandler;
use Handler\ForwardHttpHandler;
use Handler\HttpHandlerBus;
use Handler\RedirectHttpHandler;
use Logger\LoggerInterface;
use Parser\HostConfigParser;
use Parser\HttpParser;
use Router\HttpRouter;

readonly class HttpHandlerBusFactory
{
    public function create(
        HttpResponseFactory $httpResponseFactory,
        HostConfigParser $hostConfigParser,
        HttpParser $httpParser,
        HttpRouter $httpRouter,
        LoggerInterface $logger,
    ): HttpHandlerBus {
        return new HttpHandlerBus(
            $httpRouter,
            [
                HandlerConfigInterface::TYPE_FILE => new FileHttpHandler($httpResponseFactory),
                HandlerConfigInterface::TYPE_REDIRECT => new RedirectHttpHandler($httpResponseFactory),
                HandlerConfigInterface::TYPE_FORWARD => new ForwardHttpHandler($httpResponseFactory, $httpParser, $logger),
            ],
            $hostConfigParser->createAll(),
            $hostConfigParser->createDefault(),
        );
    }
}
