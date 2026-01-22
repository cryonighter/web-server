<?php

namespace Router;

use DTO\Config\HostConfig;
use DTO\HttpRequest;
use Logger\LoggerInterface;

readonly class HttpRouter
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function getConfig(HttpRequest $request, array $hostConfigs, HostConfig $defaultConfig): HostConfig
    {
        $this->logger->debug("Processing request for host {$request->getHost()}");

        foreach ($hostConfigs as $hostConfig) {
            $this->logger->debug("Checking config name: $hostConfig->name");

            if ($hostConfig->hosts && !in_array($request->getHost(), $hostConfig->hosts)) {
                $this->logger->debug("Hosts not matched");
                continue;
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

                // TODO: Протокол тут не подходит
//                if ($path->protocol && !in_array($request->protocol, $path->protocol)) {
//                    $this->logger->debug("Protocol not matched");
//                    continue;
//                }

                $this->logger->debug("Path matched");

                return $this->getConfig($request, [$path->hostConfig], $path->hostConfig);
            }

            $this->logger->debug("Not found config for paths, returning host config: $hostConfig->name");

            return $hostConfig;
        }

        $this->logger->debug("Returned default config");

        return $defaultConfig;
    }
}
