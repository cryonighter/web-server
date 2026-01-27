<?php

namespace DTO\Worker;

readonly class WorkerInfo
{
    public function __construct(
        public int $pid,
        public int $started,
    ) {
    }
}
