<?php

namespace IPC;

class SocketPair extends IPC
{
    // 8 байт (double) - updatedTime
    // 8 байт (int64) - requests
    // 8 байт (int64) - memory
    private const int PACKET_SIZE = 24;

    /** @type resource|null */
    private $masterSocket;

    /** @type resource|null */
    private $workerSocket;

    private ?IpcInfo $lastData = null;

    public function __construct($masterSocket, $workerSocket)
    {
        $this->masterSocket = $masterSocket;
        $this->workerSocket = $workerSocket;
    }

    public function thisIsMaster(): void
    {
        stream_set_blocking($this->masterSocket, false);
        fclose($this->workerSocket);
        $this->workerSocket = null;
    }

    public function thisIsWorker(): void
    {
        fclose($this->masterSocket);
        $this->masterSocket = null;
    }

    public function read(): ?IpcInfo
    {
        $message = fread($this->masterSocket, self::PACKET_SIZE);

        if ($message === false || $message === '') {
            return $this->lastData;
        }

        $this->lastData = IpcInfo::unpack($message);

        return $this->lastData;
    }

    public function write(IpcInfo $ipcInfo): void
    {
        fwrite($this->workerSocket, $ipcInfo->pack(self::PACKET_SIZE));
    }

    public function close(): void
    {
        if (isset($this->workerSocket)) {
            fclose($this->workerSocket);
            $this->workerSocket = null;
        }

        if (isset($this->masterSocket)) {
            fclose($this->masterSocket);
            $this->masterSocket = null;
        }
    }
}