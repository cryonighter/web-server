<?php

namespace DTO\Config\HandlerConfig;

use RuntimeException;

readonly class RedirectHandlerConfig implements HandlerConfigInterface
{
    public function __construct(
        public string $to,
        public int $code = 302,
    ) {
        if (!$to) {
            throw new RuntimeException('The "to" attribute is required');
        }

        if ($code < 301 || $code > 308) {
            throw new RuntimeException("Handler '$code' is not supported");
        }
    }
}
