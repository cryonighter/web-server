<?php

namespace DTO;

use RuntimeException;

class StreamBody
{
    public function __construct(private $stream)
    {
        if (!is_resource($this->stream)) {
            throw new RuntimeException("The stream body is not a resource");
        }
    }

    public function read(int $size): iterable
    {
        do {
            yield fread($this->stream, $size);
        } while (!feof($this->stream));
    }

    public function __toString(): string
    {
        return stream_get_contents($this->stream);
    }
}
