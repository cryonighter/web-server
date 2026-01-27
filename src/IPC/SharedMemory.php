<?php

namespace IPC;

use Shmop;

class SharedMemory extends IPC
{
    // 8 байт (double) - updatedTime
    // 8 байт (int64) - requests
    // 8 байт (int64) - memory
    // 8 байт - резерв
    public const int SLOT_SIZE = 32;

    public function __construct(private readonly Shmop $shm, private readonly int $offset)
    {
    }

    public function thisIsMaster(): void
    {
    }

    public function thisIsWorker(): void
    {
    }

    public function read(): ?IpcInfo
    {
        $message = shmop_read($this->shm, $this->offset, self::SLOT_SIZE);

        if ($message === false) {
            return null;
        }

        return IpcInfo::unpack($message);
    }

    public function write(IpcInfo $ipcInfo): void
    {
        shmop_write($this->shm, $ipcInfo->pack(self::SLOT_SIZE), $this->offset);
    }

    public function close(): void
    {
        // Поскольку разделяемая память общая для всех воркеров - не закрываем её
    }
}