<?php

namespace Factory;

use IPC\IPC;

interface IpcFactoryInterface
{
    public function create(int $workerId, int $maxWorkers): IPC;
}
