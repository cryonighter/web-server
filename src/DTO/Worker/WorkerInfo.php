<?php

namespace DTO\Worker;

use IPC\IPC;

readonly class WorkerInfo
{
    public function __construct(
        public int $pid,
        public IPC $ips,
        public int $started,
    ) {
    }
}
