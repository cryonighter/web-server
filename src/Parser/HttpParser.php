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

        $firstHeader = $this->parseStartLine(array_shift($headers));

        return [$firstHeader, $this->parseOtherHeaders($headers), $body];
    }

    public function parseStartLine(string $startLine): array
    {
        $startLineData = preg_split('/ +/', $startLine);

        if (count($startLineData) < 3) {
            throw new RuntimeException('Invalid start line');
        }

        return array_map(trim(...), $startLineData);
    }

    public function parseHeaderLine(string $header): array
    {
        $result = explode(':', $header, 2);

        $name = mb_convert_case(trim($result[0]), MB_CASE_TITLE, 'UTF-8');
        $value = trim($result[1] ?? '');

        return [$name, $value];
    }

    private function parseOtherHeaders(array $rows): array
    {
        $headers = [];

        foreach ($rows as $row) {
            [$name, $value] = $this->parseHeaderLine($row);

            if (!isset($headers[$name])) {
                $headers[$name] = [];
            }

            $headers[$name][] = $value;
        }

        return $headers;
    }
}
