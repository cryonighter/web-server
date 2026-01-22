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
use RuntimeException;

class HostConfigParser
{
    public function createDefault(): HostConfig
    {
        return new HostConfig(
            'default',
            './webroot',
            new FileHandlerConfig(),
            [],
            ['index.html', 'index.htm'],
            [],
        );
    }

    /**
     * @return HostConfig[]
     */
    public function createAll(): array
    {
        $pathConfig = './config';
        $pathConfigHosts = "$pathConfig/hosts";

        $configs = [];

        foreach (scandir($pathConfigHosts) as $name) {
            $file = "$pathConfigHosts/$name";

            if (!is_file($file) || !str_ends_with($file, '.xml')) {
                continue;
            }

            $document = new DOMDocument();
            $document->load($file);

            $config = $this->create($document->documentElement, $this->createDefault());

            $configs[$config->name ?: uniqid('host_config_')] = $config;
        }

        return $configs;
    }

    private function create(DOMElement $configElement, HostConfig $parentConfig): HostConfig
    {
        $webroot = null;
        $handler = null;
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

        // По задумке все дерективы "path" должны наследовать все дерективы родительского конфига
        // Однако радительский конфиг создается позже и уже должен содержать экземпляры конфигов path
        // Для решения этой проблемы создается временный конфиг, который будет передан в конфигуратор path
        $tempConfig = new HostConfig(
            $name,
            $webroot ?? $parentConfig->webroot,
            $handler ?? $parentConfig->handler,
            $hosts ?: $parentConfig->hosts,
            $indexFiles ?: $parentConfig->indexFiles,
            [],
        );

        foreach ($pathElements as $pathElement) {
            $paths[] = $this->createPath($pathElement, $tempConfig);
        }

        return new HostConfig(
            $tempConfig->name,
            $tempConfig->webroot,
            $tempConfig->handler,
            $tempConfig->hosts,
            $tempConfig->indexFiles ,
            $paths,
        );
    }

    private function createPath(DOMElement $pathElement, ?HostConfig $parentConfig): PathHostConfig
    {
        $method = strtoupper($pathElement->getAttribute('method'));
        $protocol = strtoupper($pathElement->getAttribute('protocol'));

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
}
