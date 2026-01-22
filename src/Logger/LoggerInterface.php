<?php

namespace Logger;

interface LoggerInterface
{
    public function error(string $message): void;
    public function warning(string $message): void;
    public function notice(string $message): void;
    public function info(string $message): void;
    public function debug(string $message): void;
    public function trace(string $message): void;
    public function log(LogLevel $logLevel, string $message): void;
}
