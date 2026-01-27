<?php

namespace Factory;

use IPC\SharedMemory;
use RuntimeException;
use Shmop;

class SharedMemoryIpcFactory implements IpcFactoryInterface
{
    private Shmop|false $shm = false;

    public function create(int $workerId, int $maxWorkers): SharedMemory
    {
        if (!$this->shm) {
            $key = ftok(__FILE__, 1);
            $totalSize = $maxWorkers * SharedMemory::SLOT_SIZE;

            $this->shm = shmop_open($key, 'c', 0644, $totalSize);

            if ($this->shm === false) {
                throw new RuntimeException('Failed to create shared memory');
            }
        }

        return new SharedMemory($this->shm, ($workerId - 1) * SharedMemory::SLOT_SIZE);
    }
}
