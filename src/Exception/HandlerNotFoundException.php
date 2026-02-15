<?php

namespace Exception;

use Exception;

class HandlerNotFoundException extends Exception
{
    public static function fromType(string $type): static
    {
        return new static('Handler ' . $type . ' not found', 500);
    }
}
