<?php

namespace DTO;

readonly class HttpContext
{
    public function __construct(
        public float $acceptTime,
        public string $serverAddress,
        public string $serverPort,
        public string $remoteAddress,
        public string $remotePort,
        public string $protocol,
    ) {}
}
