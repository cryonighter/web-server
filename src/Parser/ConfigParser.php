<?php

namespace Parser;

use DOMDocument;
use DOMElement;
use DTO\Config\GlobalConfig;
use DTO\Config\PreforkConfig;
use RuntimeException;

readonly class ConfigParser
{
    public function __construct(
        private HostConfigParser $hostConfigParser,
        private string $pathConfig,
    ) {
    }

    /**
     * @return GlobalConfig
     */
    public function create(): GlobalConfig
    {
        $fileConfig = "$this->pathConfig/global.xml";

        if (!is_file($fileConfig) || !str_ends_with($fileConfig, '.xml')) {
            throw new RuntimeException("File '$fileConfig' does not exist or is not xml file");
        }

        $document = new DOMDocument();
        $document->load($fileConfig);

        return $this->createGlobalConfig($document->documentElement);
    }

    private function createGlobalConfig(DOMElement $configElement): GlobalConfig
    {
        $requestSizeMax = null;
        $prefork = null;

        foreach ($configElement->childNodes as $childNode) {
            if (!$childNode instanceof DOMElement) {
                continue;
            }

            $tagName = $childNode->tagName;

            if ($tagName == 'requestSizeMax') {
                $requestSizeMax = $childNode->nodeValue;
            }

            if ($tagName == 'prefork') {
                if ($prefork) {
                    throw new RuntimeException('The config must have only one prefork');
                }
                $prefork = $this->createPreforkConfig($childNode);
            }
        }

        return new GlobalConfig(
            $requestSizeMax ?? 16777216,
            $prefork,
            $this->hostConfigParser->createAll("$this->pathConfig/hosts"),
        );
    }

    private function createPreforkConfig(DOMElement $preforkNode): PreforkConfig
    {
        $workerCount = null;
        $workerRequestLimit = null;

        foreach ($preforkNode->childNodes as $childNode) {
            if (!$childNode instanceof DOMElement) {
                continue;
            }

            $tagName = $childNode->tagName;

            var_dump($tagName);

            if ($tagName == 'workerCount') {
                if ($workerCount) {
                    throw new RuntimeException('The prefork config must have only one workerCount');
                }

                $workerCount = $childNode->nodeValue;
            }

            if ($tagName == 'workerRequestLimit') {
                if ($workerRequestLimit) {
                    throw new RuntimeException('The prefork config must have only one workerRequestLimit');
                }

                $workerRequestLimit = $childNode->nodeValue;
            }
        }

        return new PreforkConfig(
            $workerCount ?? 4,
            $workerRequestLimit ?? 1000,
        );
    }
}
