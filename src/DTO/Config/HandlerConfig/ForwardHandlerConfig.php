<?php

namespace DTO\Config\HandlerConfig;

use RuntimeException;

readonly class ForwardHandlerConfig implements HandlerConfigInterface
{
    public function __construct(
        public string $to,
    ) {
        if (!$to) {
            throw new RuntimeException('The "to" attribute is required');
        }
    }
}
