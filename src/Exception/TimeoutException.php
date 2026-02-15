<?php

namespace Exception;

use RuntimeException;
use Throwable;

class TimeoutException extends RuntimeException
{
    public function __construct(string $message, int $code = 504, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
