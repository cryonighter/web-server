<?php

class WebServerEvents
{
    public const string START = 'start';
    public const string RUN = 'run';
    public const string LISTEN = 'listen';
    public const string LISTEN_LOOP = 'listen_loop';
    public const string LISTEN_LOOP_FINALLY = 'listen_loop_finally';
    public const string LISTEN_END = 'listen_end';

    private array $events;

    public function __construct()
    {
        $this->events = [
            self::START => fn() => null,
            self::RUN => fn() => null,
            self::LISTEN => fn() => null,
            self::LISTEN_LOOP => fn() => true,
            self::LISTEN_LOOP_FINALLY => fn() => null,
            self::LISTEN_END => fn() => null,
        ];
    }

    public function setEvent(string $event, callable $listener): void
    {
        $this->events[$event] = $listener;
    }

    public function getEvent(string $event): callable
    {
        return $this->events[$event] ?? throw new RuntimeException("Event $event not found");
    }

    public function onStart(): void
    {
        $this->getEvent(self::START)();
    }

    public function onRun(): void
    {
        $this->getEvent(self::RUN)();
    }

    public function onListen(): void
    {
        $this->getEvent(self::LISTEN)();
    }

    public function onListenLoop(): bool
    {
        return $this->getEvent(self::LISTEN_LOOP)();
    }

    public function onListenLoopFinally(): void
    {
        $this->getEvent(self::LISTEN_LOOP_FINALLY)();
    }

    public function onListenEnd(): void
    {
        $this->getEvent(self::LISTEN_END)();
    }
}
