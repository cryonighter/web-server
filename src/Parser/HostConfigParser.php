<?php

namespace Parser;

use DOMDocument;
use DOMElement;
use DTO\Config\HandlerConfig\FileHandlerConfig;
use DTO\Config\HandlerConfig\ForwardHandlerConfig;
use DTO\Config\HandlerConfig\HandlerConfigInterface;
use DTO\Config\HandlerConfig\RedirectHandlerConfig;
use DTO\Config\HostConfig;
use DTO\Config\PathHostConfig;
use DTO\Config\TlsHostConfig;
use RuntimeException;

class HostConfigParser
{
    public function createDefault(): HostConfig
    {
        return new HostConfig(
            'default',
            80,
            './webroot',
            new FileHandlerConfig(),
            [],
            ['index.html', 'index.htm'],
            [],
            null,
        );
    }

    /**
     * @return HostConfig[]
     */
    public function createAll(string $pathConfig): array
    {
        $configs = [];

        foreach (scandir($pathConfig) as $name) {
            $file = "$pathConfig/$name";

            if (!is_file($file) || !str_ends_with($file, '.xml')) {
                continue;
            }

            $document = new DOMDocument();
            $document->load($file);

            $config = $this->create($document->documentElement, $this->createDefault());

            if (isset($configs[$config->port])) {
                throw new RuntimeException("A configuration with port $config->port already exists");
            }

            $configs[$config->port] = $config;
        }

        return $configs;
    }

    private function create(DOMElement $configElement, HostConfig $parentConfig): HostConfig
    {
        $webroot = null;
        $handler = null;
        $tls = null;
        $hosts = [];
        $indexFiles = [];
        $paths = [];

        $pathElements = [];

        foreach ($configElement->childNodes as $childNode) {
            if (!$childNode instanceof DOMElement) {
                continue;
            }

            $tagName = $childNode->tagName;

            if ($tagName == 'webroot') {
                $webroot = $childNode->nodeValue;
            }

            if ($tagName == 'handler') {
                if ($handler) {
                    throw new RuntimeException('The config must have only one handler');
                }
                $handler = $this->createHandlerConfig($childNode);
            }

            if ($tagName == 'tls') {
                if ($tls) {
                    throw new RuntimeException('The config must have only one tls');
                }
                $tls = $this->createTLSConfig($childNode);
            }

            if ($tagName == 'host') {
                $hosts[] = $childNode->nodeValue;
            }

            if ($tagName == 'indexFile') {
                $indexFiles[] = $childNode->nodeValue;
            }

            if ($tagName == 'path') {
                $pathElements[] = $childNode;
            }
        }

        $name = $configElement->getAttribute('name');
        $port = $configElement->getAttribute('port');

        // По задумке все дерективы "path" должны наследовать все дерективы родительского конфига
        // Однако радительский конфиг создается позже и уже должен содержать экземпляры конфигов path
        // Для решения этой проблемы создается временный конфиг, который будет передан в конфигуратор path
        $tempConfig = new HostConfig(
            $name,
            $port ?: $parentConfig->port,
            $webroot ?? $parentConfig->webroot,
            $handler ?? $parentConfig->handler,
            $hosts ?: $parentConfig->hosts,
            $indexFiles ?: $parentConfig->indexFiles,
            [],
            $tls ?? $parentConfig->tls,
        );

        foreach ($pathElements as $pathElement) {
            $paths[] = $this->createPath($pathElement, $tempConfig);
        }

        return new HostConfig(
            $tempConfig->name,
            $tempConfig->port,
            $tempConfig->webroot,
            $tempConfig->handler,
            $tempConfig->hosts,
            $tempConfig->indexFiles ,
            $paths,
            $tempConfig->tls,
        );
    }

    private function createPath(DOMElement $pathElement, ?HostConfig $parentConfig): PathHostConfig
    {
        $method = strtoupper($pathElement->getAttribute('method'));
        $protocol = strtoupper($pathElement->getAttribute('protocol'));

        if ($pathElement->getElementsByTagName('tls')->length > 0) {
            throw new RuntimeException('The tls directive is not supported in the path directive');
        }

        return new PathHostConfig(
            $pathElement->getAttribute('name'),
            $pathElement->getAttribute('regexp') ?: '.*',
            $method ? explode('|', $method) : [],
            $protocol ? explode('|', $protocol) : [],
            $this->create($pathElement, $parentConfig),
        );
    }

    private function createHandlerConfig(DOMElement $handlerElement): HandlerConfigInterface
    {
        $type = $handlerElement->getAttribute('type');
        $to = $handlerElement->getAttribute('to');
        $code = $handlerElement->getAttribute('code') ?: 302;

        return match ($type) {
            HandlerConfigInterface::TYPE_FILE => new FileHandlerConfig(),
            HandlerConfigInterface::TYPE_FORWARD => new ForwardHandlerConfig($to),
            HandlerConfigInterface::TYPE_REDIRECT => new RedirectHandlerConfig($to, $code),
            default => throw new RuntimeException("Handler '$type' is not supported"),
        };
    }

    private function createTLSConfig(DOMElement $tlsNode): TlsHostConfig
    {
        $certificate = null;
        $privateKey = null;
        $securityLevel = null;

        foreach ($tlsNode->childNodes as $childNode) {
            if (!$childNode instanceof DOMElement) {
                continue;
            }

            $tagName = $childNode->tagName;

            if ($tagName == 'certificate') {
                if ($certificate) {
                    throw new RuntimeException('The tls config must have only one certificate');
                }

                if (!file_exists($childNode->nodeValue) || !is_readable($childNode->nodeValue)) {
                    throw new RuntimeException("Certificate file '$childNode->nodeValue' does not exist or is not readable");
                }

                $certificate = $childNode->nodeValue;
            }

            if ($tagName == 'privateKey') {
                if ($privateKey) {
                    throw new RuntimeException('The tls config must have only one privateKey');
                }

                if (!file_exists($childNode->nodeValue) || !is_readable($childNode->nodeValue)) {
                    throw new RuntimeException("PrivateKey file '$childNode->nodeValue' does not exist or is not readable");
                }

                $privateKey = $childNode->nodeValue;
            }

            if ($tagName == 'securityLevel') {
                if ($securityLevel) {
                    throw new RuntimeException('The tls config must have only one privateKey');
                }

                if (!in_array($childNode->nodeValue, [0, 1, 2, 3, 4, 5])) {
                    throw new RuntimeException("Security level '$childNode->nodeValue' is not supported");
                }

                $securityLevel = $childNode->nodeValue;
            }
        }

        return new TlsHostConfig(
            $certificate ?? throw new RuntimeException('The tls config must have certificate'),
            $privateKey ?? throw new RuntimeException('The tls config must have privateKey'),
            $securityLevel ?? 2,
        );
    }
}
