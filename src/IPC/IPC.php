<?php

namespace IPC;

abstract class IPC
{
    abstract public function thisIsMaster(): void;
    abstract public function thisIsWorker(): void;

    abstract public function read(): ?IpcInfo;
    abstract public function write(IpcInfo $ipcInfo): void;
    abstract public function close(): void;
}
