<?php

namespace IPC;

use RuntimeException;

readonly class IpcInfo
{
    public function __construct(
        public float $updatedTime,
        public int $requests,
        public int $memory,
    ) {
    }

    public static function create(int $requests): static
    {
        return new static(microtime(true), $requests, memory_get_usage());
    }

    public static function unpack(string $packed): ?static
    {
        $unpacked = unpack('dupdatedTime/Jrequests/Jmemory', $packed);

        if ($unpacked === false) {
            throw new RuntimeException('Failed to unpack IPC info');
        }

        return new static(...$unpacked);
    }

    public function pack(int $size): string
    {
        $packed = pack(
            'dJJ',
            $this->updatedTime,
            $this->requests,
            $this->memory,
        );

        return str_pad($packed, $size, "\0");
    }
}
