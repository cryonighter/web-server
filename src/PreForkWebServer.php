<?php

use DTO\Worker\WorkerInfo;
use Factory\HttpRequestFactory;
use Factory\HttpResponseFactory;
use Factory\IpcFactoryInterface;
use Handler\HttpHandlerBus;
use IPC\IpcInfo;
use Logger\LoggerInterface;
use Parser\HostConfigParser;

class PreForkWebServer extends WebServer
{
    private const int WORKER_COUNT = 4;
    private const int MAX_REQUESTS_PER_WORKER = 1000;

    public function __construct(
        LoggerInterface $logger,
        HostConfigParser $hostConfigParser,
        HttpHandlerBus $httpHandlerBus,
        HttpRequestFactory $httpRequestFactory,
        HttpResponseFactory $httpResponseFactory,
        private readonly IpcFactoryInterface $ipcFactory,
    ) {
        parent::__construct($logger, $hostConfigParser, $httpHandlerBus, $httpRequestFactory, $httpResponseFactory);
    }

    /**
     * @type  WorkerInfo[]
     */
    private array $workers = [];

    protected function run(array $sockets, array $hostConfigs): void
    {
        $this->logger->info('Master process started with PID: ' . getmypid());

        pcntl_async_signals(true);

        pcntl_signal(SIGTERM, fn() => $this->handleShutdown());
        pcntl_signal(SIGINT, fn() => $this->handleShutdown());
        pcntl_signal(SIGQUIT, fn() => $this->handleShutdown());
        pcntl_signal(SIGCHLD, fn() => $this->handleWorkerExit($sockets, $hostConfigs));

        try {
            for ($i = 1; $i <= self::WORKER_COUNT; $i++) {
                $this->fork($sockets, $hostConfigs, $i);
            }

            $this->logger->info('All workers spawned');

            while (!$this->shutdown) {
                // Проверка сообщений от воркеров (каждые 5 секунд)
                foreach ($this->workers as $id => $workerInfo) {
                    $ipcInfo = $workerInfo->ips->read();

                    if ($ipcInfo) {
                        $this->logger->debug("Worker #$id processed $ipcInfo->requests requests");
                        $this->logger->debug("Worker #$id used $ipcInfo->memory bytes memory");
                    } else {
                        $this->logger->warning("Worker #$id didn't send any data");
                    }
                }

                usleep(5000000); // 5s
            }
        } catch (Throwable $exception) {
            $this->logger->error($exception);
        } finally {
            $this->stopAllWorkers($sockets);

            $this->logger->info('Server stopped');
        }
    }

    private function fork(array $sockets, array $hostConfigs, int $id): void
    {
        $ips = $this->ipcFactory->create($id, self::WORKER_COUNT);

        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new RuntimeException('Failed to fork worker process');
        }

        if ($pid === 0) {
            $ips->thisIsWorker();
            $ips->write(IpcInfo::create($this->processedRequests));

            $this->logger->setWorkerId($id);

            $this->events->setEvent(
                WebServerEvents::LISTEN_LOOP,
                fn() => $this->processedRequests < self::MAX_REQUESTS_PER_WORKER,
            );

            $this->events->setEvent(
                WebServerEvents::LISTEN_LOOP_FINALLY,
                function () use ($ips) {
                    $ips->write(IpcInfo::create($this->processedRequests));
                },
            );

            $this->events->setEvent(
                WebServerEvents::LISTEN_END,
                function () use ($ips) {
                    if ($this->shutdown) {
                        $this->logger->info('Stopped worker by signal from master');
                    } else {
                        $this->logger->info('Stopped worker by reached max requests limit (' . self::MAX_REQUESTS_PER_WORKER . ')');
                    }
                    $ips->close();
                },
            );

            $this->listen($sockets, $hostConfigs);

            exit(0);
        }

        $ips->thisIsMaster();

        $this->workers[$id] = new WorkerInfo($pid, $ips, time());

        $this->logger->info("Worker #$id spawned with PID: $pid");
    }

    private function stopAllWorkers(array $sockets): void
    {
        $this->logger->info('Stopping all workers...');

        // Даем воркерам 10 секунд на graceful shutdown
        $this->waitStopingWorkers(10, 100000);

        // Принудительно убиваем оставшихся воркеров
        foreach ($this->workers as $id => $workerInfo) {
            $this->logger->warning("Force killing worker #$id (PID: $workerInfo->pid)");

            posix_kill($workerInfo->pid, SIGKILL);
        }

        // Ждем принудительной остановки еще 2 секунды
        $this->waitStopingWorkers(2, 50000);

        // Закрываем сокеты
        foreach ($sockets as $socket) {
            fclose($socket);
        }

        if (!empty($this->workers)) {
            throw new RuntimeException('Failed killing workers: ' . implode(', ', array_keys($this->workers)));
        }
    }

    private function waitStopingWorkers(int $timeout, int $interval): void
    {
        $timeout = time() + $timeout;
        while (!empty($this->workers) && time() < $timeout) {
            usleep($interval);
        }
    }

    private function handleWorkerExit(array $sockets, array $hostConfigs): void
    {
        // Обрабатываем все завершившиеся процессы
        while (($pid = pcntl_waitpid(-1, $status, WNOHANG)) > 0) {
            $workerId = array_find_key($this->workers, fn(WorkerInfo$workerInfo): bool => $workerInfo->pid === $pid);

            if ($workerId === null) {
                $this->logger->warning("Unknown process with PID $pid exited");
                continue;
            }

            $workerInfo = $this->workers[$workerId];

            $signalCode = pcntl_wifsignaled($status) ? pcntl_wtermsig($status) : null;
            $exitCode = pcntl_wexitstatus($status);

            if ($signalCode) {
                $this->logger->warning("Worker #$workerId (PID: $pid) killed by signal: $signalCode");
            } else {
                $this->logger->info("Worker #$workerId (PID: $pid) exited with code: $exitCode");
            }

            $workerInfo->ips->close();

            unset($this->workers[$workerId]);

            if (!$this->shutdown) {
                $this->logger->info("Respawning worker #$workerId");
                $this->fork($sockets, $hostConfigs, $workerId);
            }
        }
    }
}
