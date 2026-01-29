<?php

namespace DTO\Config;

readonly class PreforkConfig
{
    public function __construct(
        public int $workerCount,
        public int $workerRequestLimit,
    ) {
    }
}
