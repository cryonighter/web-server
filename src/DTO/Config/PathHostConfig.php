<?php

namespace DTO\Config;

readonly class PathHostConfig
{
    public function __construct(
        public string $name,
        public string $regexp,
        public array $methods,
        public array $protocol,
        public HostConfig $hostConfig,
    ) {
    }
}
