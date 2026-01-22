<?php

namespace Handler;

use DTO\HttpRequest;
use DTO\HttpResponse;
use Exception\HttpException;
use Factory\HttpResponseFactory;

readonly class RedirectHttpHandler
{
    private const array CODES = [
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
    ];

    public function __construct(private HttpResponseFactory $httpResponseFactory)
    {
    }

    /**
     * @throws HttpException
     */
    public function handle(HttpRequest $request, string $to, int $code): HttpResponse
    {
        if (!isset(self::CODES[$code])) {
            throw HttpException::createFromCode(501);
        }

        return $this->httpResponseFactory->create(
            'HTTP/1.1',
            $code,
            self::CODES[$code],
            [
                'Location' => [$to],
            ],
        );
    }
}
