<?php

namespace Factory;

use DTO\HttpContext;
use DTO\HttpRequest;
use Exception\HttpHandlerException;

class CgiEnvFactory
{
    /**
     * Формирует переменные окружения согласно CGI/1.1 RFC 3875
     */
    public function create(HttpRequest $request, HttpContext $context, string $webroot, string $executable): array
    {
        $server = explode(':', $request->getHost() ?? '');
        $host = $server[0] ?? '';
        $port = $server[1] ?? ($context->protocol === 'HTTPS' ? 443 : 80);

        if (!$host) {
            throw new HttpHandlerException('Missing Host header');
        }

        // Обработка кейсов вроде /script.php/extra/info и /app.cgi/user/123
        [$scriptName, $pathInfo] = $this->parsePathInfo($request->getPathWithoutQuery());

        $remoteAddr = $this->getRemoteAddress($request, $context);

        $env = [
            'GATEWAY_INTERFACE' => 'CGI/1.1',
            'SERVER_PROTOCOL' => $request->protocol,
            'REQUEST_METHOD' => $request->method,
            'QUERY_STRING' => $request->queryString,
            'SCRIPT_NAME' => $scriptName,
            'PATH_INFO' => $pathInfo,
            'PATH_TRANSLATED' => ($pathInfo && $webroot) ? ($webroot . $pathInfo) : '',
            // Информация о сервере
            'SERVER_NAME' => $host,
            'SERVER_PORT' => $port,
            'SERVER_SOFTWARE' => 'PHPWebServer/0.3',
            // Информация о клиенте
            'REMOTE_ADDR' => $remoteAddr,
            'REMOTE_HOST' => $remoteAddr,
            // Не входит в спецификацию CGI/1.1 RFC 3875, но является общепринятой практикой
            'REMOTE_PORT' => $context->remotePort,
            'REQUEST_URI' => $request->path,
            'REQUEST_TIME' => (string) floor($context->acceptTime),
            'HTTPS' => $context->protocol === 'HTTPS' ? 'on' : '',
            'SERVER_ADDR' => $context->serverAddress,
            'SCRIPT_URI' => strtolower($context->protocol) . "://$host" . (!in_array($port, [80, 443]) ? ":$port" : '') . $scriptName,
            'SCRIPT_URL' => $scriptName,
        ];

        if ($this->isPhp($scriptName, $executable)) {
            $env['ORIG_PATH_INFO'] = $pathInfo;
            $env['PHP_SELF'] = $scriptName . $pathInfo;
            $env['REQUEST_TIME_FLOAT'] = (string) $context->acceptTime;
            // Статус редиректа требуется для PHP-CGI с force-cgi-redirect
            // Значение 200 означает успешный редирект от веб-сервера
            $env['REDIRECT_STATUS'] = '200';
        }

        if ($webroot) {
            $env['DOCUMENT_ROOT'] = $webroot;
            $env['SCRIPT_FILENAME'] = $webroot . $request->getPathWithoutQuery();
        }

        if (isset($request->headers['Content-Length'][0])) {
            $env['CONTENT_LENGTH'] = $request->headers['Content-Length'][0];
        }

        if (isset($request->headers['Content-Type'][0])) {
            $env['CONTENT_TYPE'] = $request->headers['Content-Type'][0];
        }

        if (isset($request->headers['Content-Encoding'][0])) {
            $env['CONTENT_ENCODING'] = $request->headers['Content-Encoding'][0];
        }

        if (isset($request->headers['Authorization'][0])) {
            [$authType, $remoteUser] = $this->parseAuthentication($request->headers['Authorization'][0]);

            if ($authType) {
                $env['AUTH_TYPE'] = $authType;
            }

            if ($remoteUser) {
                $env['REMOTE_USER'] = $remoteUser;
            }
        }

        foreach ($request->headers as $name => $values) {
            $headerName = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

            if ($name == 'Authorization') {
                $env[$headerName] = $values[0] ?? '';
            } else {
                $env[$headerName] = implode(', ', $values);
            }
        }

        return $env;
    }

    /**
     * Разделяет путь на SCRIPT_NAME и PATH_INFO
     */
    private function parsePathInfo(string $path): array
    {
        if (preg_match('#^(/[^?]*?\.[a-zA-Z]+)(/.*)?$#', $path, $matches)) {
            return [
                $matches[1],       // SCRIPT_NAME - путь до расширения файла включительно
                $matches[2] ?? '', // PATH_INFO - всё что после
            ];
        }

        return [$path, ''];
    }

    /**
     * Определяет реальный IP-адрес клиента с учетом прокси/балансировщиков
     */
    private function getRemoteAddress(HttpRequest $request, HttpContext $context): string
    {
        // X-Forwarded-For: стандарт де-факто, может содержать список "client, proxy1, proxy2"
        $forwarded = $request->headers['X-Forwarded-For'][0] ?? null;
        if ($forwarded) {
            return trim(explode(',', $forwarded)[0]);
        }

        // X-Real-IP: используется nginx, содержит один IP
        if (isset($request->headers['X-Real-IP'][0])) {
            return trim($request->headers['X-Real-IP'][0]);
        }

        // IP из соединения
        return $context->remoteAddress;
    }

    /**
     * Парсит заголовок Authorization и возвращает переменные AUTH_TYPE и REMOTE_USER
     */
    private function parseAuthentication(string $authorization): array
    {
        // Basic аутентификация: "Basic base64(username:password)"
        $matches = [];
        if (preg_match('/^Basic\s+(.+)$/i', $authorization, $matches)) {
            return ['Basic', explode(':', base64_decode($matches[1]), 2)[0]];
        }

        // Digest аутентификация: "Digest username="user", realm="..."
        $matches = [];
        if (preg_match('/^Digest\s+(.+)$/i', $authorization, $matches)) {
            preg_match('/username="?([^",\s]+)"?/', $matches[1], $userMatch);

            return ['Digest', $userMatch[1] ?? null];
        }

        // Bearer, NTLM и другие типы - без REMOTE_USER
        $matches = [];
        if (preg_match('/^(\w+)\s+/i', $authorization, $matches)) {
            return [$matches[1], null];
        }

        return [null, null];
    }

    private function isPhp(string $scriptName, string $executable): bool
    {
        // .php, .php5, .php8
        if (preg_match('/\.php\d*(\?|\/|$)/i', $scriptName)) {
            return true;
        }

        if (str_contains($executable, 'php')) {
            return true;
        }

        return false;
    }
}
