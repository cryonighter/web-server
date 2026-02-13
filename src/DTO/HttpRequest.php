<?php

namespace DTO;

class HttpRequest
{
    private(set) string $queryString = '';
    private(set) array $query = [];

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
        $this->queryString = parse_url($path)['query'] ?? '';
        parse_str($this->queryString, $this->query);
    }

    public function getPathWithoutQuery(): string
    {
        return parse_url($this->path)['path'] ?? '';
    }

    public function getHost(): ?string
    {
        return current($this->headers['Host'] ?? []) ?: null;
    }

    public function __toString(): string
    {
        return $this->source;
    }
}
