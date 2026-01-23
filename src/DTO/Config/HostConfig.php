<?php

namespace DTO\Config;

use DTO\Config\HandlerConfig\HandlerConfigInterface;

class HostConfig
{
    public function __construct(
        public string $name,
        public int $port,
        public string $webroot,
        public HandlerConfigInterface $handler,
        public array $hosts,
        public array $indexFiles,
        /** @type PathHostConfig[] */
        public array $paths,
        public ?TlsHostConfig $tls,
    ) {
    }
}
