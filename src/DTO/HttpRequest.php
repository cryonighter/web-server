<?php

namespace DTO;

class HttpRequest
{
    private array $query = [];

    public string $startLine {
        get => "$this->method $this->path $this->protocol";
    }

    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly string $protocol,
        public readonly array $headers,
        public readonly string $body,
        public readonly string $source,
    ) {
        parse_str(parse_url($path)['query'] ?? '', $this->query);
    }

    public function getPathWithoutQuery(): string
    {
        return parse_url($this->path)['path'] ?? '';
    }

    public function getHost(): ?string
    {
        return current($this->headers['Host'] ?? []) ?: null;
    }

    public function getQuery(): array
    {
        return $this->query;
    }

    public function __toString(): string
    {
        return $this->source;
    }
}
