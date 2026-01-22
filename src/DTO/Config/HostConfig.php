<?php

namespace DTO\Config;

use DTO\Config\HandlerConfig\HandlerConfigInterface;

class HostConfig
{
    public function __construct(
        public string $name,
        public string $webroot,
        public HandlerConfigInterface $handler,
        public array $hosts,
        public array $indexFiles,
        /** @type PathHostConfig[] */
        public array $paths,
    ) {
    }
}
