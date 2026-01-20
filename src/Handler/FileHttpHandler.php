<?php

namespace Handler;

use DTO\HttpRequest;
use DTO\HttpResponse;
use Exception\HttpException;
use Factory\HttpResponseFactory;

readonly class FileHttpHandler
{
    public function __construct(private HttpResponseFactory $httpResponseFactory)
    {
    }

    /**
     * @throws HttpException
     */
    public function handle(HttpRequest $request, string $webroot): HttpResponse
    {
        if (!in_array($request->method, ['GET', 'HEAD'])) {
            throw HttpException::createFromCode(405);
        }

        $file = $webroot . $request->path;

        if (!$request->path || $request->path == '/') {
            $file = $this->getIndexFile($webroot);
        }

        // Дабы не давать пользователю информацию о наличии/отсутствии
        // файла по 403/404 кодам - проверять будем директорию
        $dir = realpath(is_dir($file) ? $file : dirname($file));

        if ($dir && !str_starts_with($dir, realpath($webroot))) {
            throw HttpException::createFromCode(403);
        }

        if (!file_exists($file) || is_dir($file)) {
            throw HttpException::createFromCode(404);
        }

        if (!is_readable($file)) {
            throw HttpException::createFromCode(403);
        }

        return $this->httpResponseFactory->create(
            'HTTP/1.1',
            200,
            'OK',
            [
                'Content-Length' => [filesize($file)],
                'Content-Type' => [mime_content_type($file)],
            ],
            $request->method === 'HEAD' ? '' : file_get_contents($file),
        );
    }

    /**
     * @throws HttpException
     */
    private function getIndexFile(string $webroot): string
    {
        foreach (['index.html', 'index.htm'] as $defaultIndexFile) {
            $file = "$webroot/$defaultIndexFile";

            if (file_exists($file) && is_readable($file) && !is_dir($file)) {
                return $file;
            }
        }

        throw HttpException::createFromCode(404);
    }
}
