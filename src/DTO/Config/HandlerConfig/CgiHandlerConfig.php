<?php

namespace DTO\Config\HandlerConfig;

use RuntimeException;

readonly class CgiHandlerConfig implements HandlerConfigInterface
{
    public function __construct(
        public string $executable,
    ) {
        if (!$executable) {
            throw new RuntimeException('The "executable" attribute is required');
        }
    }
}
