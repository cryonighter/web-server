<?php

namespace Logger;

class StdoutLogger implements LoggerInterface
{
    public function __construct(private LogLevel $logLevel, private string $workerId = '')
    {
    }

    public function error(string $message): void
    {
        $this->log(LogLevel::ERROR, $message);
    }

    public function warning(string $message): void
    {
        $this->log(LogLevel::WARNING, $message);
    }

    public function notice(string $message): void
    {
        $this->log(LogLevel::NOTICE, $message);
    }

    public function info(string $message): void
    {
        $this->log(LogLevel::INFO, $message);
    }

    public function debug(string $message): void
    {
        $this->log(LogLevel::DEBUG, $message);
    }

    public function trace(string $message): void
    {
        $this->log(LogLevel::TRACE, $message);
    }

    public function log(LogLevel $logLevel, string $message): void
    {
        if ($this->logLevel->value >= $logLevel->value) {
            fwrite(
                $logLevel->value <= LogLevel::ERROR->value ? STDERR : STDOUT,
                $this->format($logLevel, $message) . PHP_EOL,
            );
        }
    }

    public function getLogLevel(): LogLevel
    {
        return $this->logLevel;
    }

    public function setLogLevel(LogLevel $logLevel): void
    {
        $this->logLevel = $logLevel;
    }

    public function setWorkerId(string $workerId): void
    {
        $this->workerId = $workerId;
    }

    private function format(LogLevel $logLevel, string $message): string
    {
        if ($this->workerId) {
            return '[' . date('Y-m-d H:i:s') . '] [' . $logLevel->name . '] [Worker #' . $this->workerId . '] ' . $message;
        }

        return '[' . date('Y-m-d H:i:s') . '] [' . $logLevel->name . '] ' . $message;
    }
}
