<?php

namespace Parser;

use RuntimeException;

class HttpParser
{
    public function parse(string $content): array
    {
        $blocks = preg_split('/\r?\n\r?\n/', $content, 2);

        $headers = preg_split('/\r?\n/', $blocks[0]);
        $body = $blocks[1] ?? '';

        if (!$headers) {
            throw new RuntimeException('Empty http request headers');
        }

        $firstHeader = $this->parseFirstHeader(array_shift($headers));

        return [$firstHeader, $this->parseOtherHeaders($headers), $body];
    }

    public function parseFirstHeader(string $row): array
    {
        $firstHeader = preg_split('/ +/', $row);

        if (count($firstHeader) < 3) {
            throw new RuntimeException('Invalid headers content');
        }

        return $firstHeader;
    }

    private function parseOtherHeaders(array $rows): array
    {
        $headers = [];

        foreach ($rows as $row) {
            $result = explode(':', $row, 2);

            $name = trim($result[0]);
            $value = trim($result[1] ?? '');

            if (!isset($headers[$name])) {
                $headers[$name] = [];
            }

            $headers[$name][] = $value;
        }

        ksort($headers);

        return $headers;
    }
}
