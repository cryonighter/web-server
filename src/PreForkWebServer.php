<?php

use DTO\Worker\WorkerInfo;

class PreForkWebServer extends WebServer
{
    private const int WORKER_COUNT = 4;
    private const int MAX_REQUESTS_PER_WORKER = 1000;

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

        try {
            for ($i = 1; $i <= self::WORKER_COUNT; $i++) {
                $this->fork($sockets, $hostConfigs, $i);
            }

            $this->logger->info('All workers spawned');

            $workerInfoIteration = 0;

            while (!$this->shutdown) {
                // Проверка работоспособности воркеров
                foreach ($this->workers as $id => $workerInfo) {
                    $status = 0;

                    if (pcntl_waitpid($workerInfo->pid, $status, WNOHANG) > 0) {
                        $signalCode = pcntl_wifsignaled($status) ? pcntl_wtermsig($status) : null;
                        $exitCode = pcntl_wexitstatus($status);

                        if ($signalCode) {
                            $this->logger->warning("Worker #$id (PID: $workerInfo->pid) killed by signal: $signalCode");
                        } else {
                            $this->logger->info("Worker #$id (PID: $workerInfo->pid) exited with code: $exitCode");
                        }

                        $workerInfo->ips->close();

                        unset($this->workers[$id]);

                        if (!$this->shutdown) {
                            $this->logger->info("Respawning worker #$id");
                            $this->fork($sockets, $hostConfigs, $id);
                        }
                    }
                }

                usleep(100000); // 0.1s
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
        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new RuntimeException('Failed to fork worker process');
        }

        if ($pid === 0) {
            $this->logger->setWorkerId($id);

            $this->events->setEvent(
                WebServerEvents::LISTEN_LOOP,
                fn() => $this->processedRequests < self::MAX_REQUESTS_PER_WORKER,
            );

            $this->events->setEvent(
                WebServerEvents::LISTEN_END,
                function () {
                    if ($this->shutdown) {
                        $this->logger->info('Stopped worker by signal from master');
                    } else {
                        $this->logger->info('Stopped worker by s reached max requests limit (' . self::MAX_REQUESTS_PER_WORKER . ')');
                    }
                },
            );

            $this->listen($sockets, $hostConfigs);

            exit(0);
        }

        $this->workers[$id] = new WorkerInfo($pid, time());

        $this->logger->info("Worker #$id spawned with PID: $pid");
    }

    private function stopAllWorkers(array $sockets): void
    {
        $this->logger->info('Stopping all workers...');

        // Даем воркерам 10 секунд на graceful shutdown
        $timeout = time() + 10;
        while (!empty($this->workers) && time() < $timeout) {
            foreach ($this->workers as $id => $workerInfo) {
                if (pcntl_waitpid($workerInfo->pid, $status, WNOHANG) > 0) {
                    $this->logger->info("Worker #$id stopped");

                    unset($this->workers[$id]);
                }
            }
            usleep(100000);
        }

        // Принудительно убиваем оставшихся воркеров
        foreach ($this->workers as $id => $workerInfo) {
            $this->logger->warning("Force killing worker #$id (PID: $workerInfo->pid)");

            posix_kill($workerInfo->pid, SIGKILL);
            pcntl_waitpid($workerInfo->pid, $status);

            $this->logger->info("Worker #$id killed");

            unset($this->workers[$id]);
        }

        // Закрываем сокеты
        foreach ($sockets as $socket) {
            fclose($socket);
        }
    }
}
