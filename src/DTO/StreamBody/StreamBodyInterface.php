<?php

namespace DTO\StreamBody;

interface StreamBodyInterface
{
    public function read(int $size): iterable;

    public function __toString(): string;
}
