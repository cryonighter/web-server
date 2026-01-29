<?php

namespace DTO\Config;

readonly class TlsHostConfig
{
    public function __construct(
        public string $certificate,
        public string $privateKey,
        public string $securityLevel,
    ) {
    }
}
