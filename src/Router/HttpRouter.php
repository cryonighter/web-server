<?php

namespace Router;

use DTO\Config\HostConfig;
use DTO\HttpContext;
use DTO\HttpRequest;
use Exception\HttpException;
use Logger\LoggerInterface;

readonly class HttpRouter
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    /**
     * @throws HttpException
     */
    public function getRouteConfig(HttpRequest $request, HttpContext $context, HostConfig $hostConfig): HostConfig
    {
        $this->logger->debug("Processing request for host {$request->getHost()}");

        if ($hostConfig->hosts && !in_array($request->getHost(), $hostConfig->hosts)) {
            $this->logger->debug("Hosts not matched in config: $hostConfig->name");

            throw HttpException::createFromCode(500);
        }

        foreach ($hostConfig->paths as $path) {
            $this->logger->debug("Checking path name: $path->name");

            if ($path->regexp && !preg_match("/$path->regexp/", $request->path)) {
                $this->logger->debug("Path regexp not matched");
                continue;
            }

            if ($path->methods && !in_array($request->method, $path->methods)) {
                $this->logger->debug("Methods not matched");
                continue;
            }

            if ($path->protocol && !in_array($context->protocol, $path->protocol)) {
                $this->logger->debug("Protocol not matched");
                continue;
            }

            $this->logger->debug("Path matched");

            return $this->getRouteConfig($request, $context, $path->hostConfig);
        }

        $this->logger->debug("Not found config for paths, returning host config: $hostConfig->name");

        return $hostConfig;
    }
}
