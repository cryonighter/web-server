<?php

namespace Exception;

use Exception;
use Throwable;

class HttpException extends Exception
{
    public const array CODES = [
        // 4xx
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Payload Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',

        // 5xx
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        509 => 'Bandwidth Limit Exceeded',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
        520 => 'Unknown Error',
        521 => 'Web Server Is Down',
    ];

    public static function createFromCode(int $code, ?Throwable $exception = null): static
    {
        $message = static::CODES[$code] ?? null;

        if (!$message) {
            return new static(self::CODES[500], 500, $exception);
        }

        return new static($message, $code, $exception);
    }

    public function is4xx(): bool
    {
        return $this->code >= 400 && $this->code < 500;

    }

    public function is5xx(): bool
    {
        return $this->code >= 500 && $this->code < 600;
    }
}
