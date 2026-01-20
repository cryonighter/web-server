<?php

namespace DTO;

readonly class HttpResponse
{
    public function __construct(
        public string $protocol,
        public int $code,
        public string $message,
        public array $headers,
        public StreamBody|string $body = '',
    ) {
    }

    public function read(int $size): iterable
    {
        $headersBlock = $this->getHeadersBlock() . (is_string($this->body) ? $this->body : '');
        $headersBlockSize = strlen($headersBlock);
        $headersBlockPosition = 0;

        do {
            $chunk = substr($headersBlock, $headersBlockPosition, $size);
            $chunkSize = strlen($chunk);
            $headersBlockPosition += $chunkSize;

            yield $chunk;
        } while ($headersBlockPosition < $headersBlockSize);

        if ($this->body instanceof StreamBody) {
            yield $this->body->read($size);
        }
    }

    public function __toString(): string
    {
        return $this->getHeadersBlock() . $this->body;
    }

    private function getHeadersBlock(): string
    {
        $headers = [];

        foreach ($this->headers as $key => $values) {
            foreach ($values as $value) {
                $headers[] = $key . ': ' . $value;
            }
        }

        return "$this->protocol $this->code $this->message\r\n" . implode("\r\n", $headers) . "\r\n\r\n";
    }
}
