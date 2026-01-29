<?php

namespace DTO\Config;

readonly class GlobalConfig
{
    public function __construct(
        public int $requestSizeMax,
        public ?PreforkConfig $prefork,
        public array $hosts,
    ) {
    }
}
