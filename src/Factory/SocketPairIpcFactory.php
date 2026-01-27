<?php

namespace Factory;

use IPC\SocketPair;
use RuntimeException;

class SocketPairIpcFactory implements IpcFactoryInterface
{
    public function create(int $workerId, int $maxWorkers): SocketPair
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        if ($pair === false) {
            throw new RuntimeException('Failed to create socket pair for IPC');
        }

        [$masterSocket, $workerSocket] = $pair;

        return new SocketPair($masterSocket, $workerSocket);
    }
}
