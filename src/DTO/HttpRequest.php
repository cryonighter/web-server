<?php

namespace DTO;

readonly class HttpRequest
{
    public function __construct(
        public string $method,
        public string $path,
        public string $protocol,
        public array $headers,
        public string $body,
        public string $source,
    ) {}

    public function getHost(): ?string
    {
        return current($this->headers['Host']) ?: null;
    }
}
