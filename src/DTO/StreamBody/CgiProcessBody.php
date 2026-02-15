<?php

namespace DTO\StreamBody;

class CgiProcessBody implements StreamBodyInterface
{
    public function __construct(private $process, public $stdout, private $stderr)
    {
    }

    public function __destruct()
    {
        $this->close();
    }

    public function close(): int
    {
        if (is_resource($this->stdout)) {
            fclose($this->stdout);
        }

        if (is_resource($this->stderr)) {
            fclose($this->stderr);
        }

        if (is_resource($this->process)) {
            return proc_close($this->process);
        }

        return -1;
    }

    public function read(int $size): iterable
    {
        do {
            yield fread($this->stdout, $size);
        } while (!feof($this->stdout));
    }

    public function __toString(): string
    {
        return stream_get_contents($this->stdout);
    }
}
