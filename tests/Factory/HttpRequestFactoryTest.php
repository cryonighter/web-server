<?php

namespace Tests\Factory;

use Factory\HttpRequestFactory;
use Parser\HttpParser;
use Exception\HttpException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class HttpRequestFactoryTest extends TestCase
{
    private HttpRequestFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new HttpRequestFactory(new HttpParser());
    }

    /**
     * Создает stream из строки для тестирования
     */
    private function createStream(string $content)
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $content);
        rewind($stream);
        return $stream;
    }

    /**
     * Тест создания экземпляра HttpRequest с минимальными данными
     *
     * @throws HttpException
     */
    public function testCreateFromStreamSimpleGetRequest(): void
    {
        $content = "GET /index.html HTTP/1.1\r\n" .
            "Host: example.com\r\n" .
            "\r\n";

        $stream = $this->createStream($content);

        $request = $this->factory->createFromStream($stream, 8192);

        $this->assertEquals('GET', $request->method);
        $this->assertEquals('/index.html', $request->path);
        $this->assertEquals('HTTP/1.1', $request->protocol);
        $this->assertArrayHasKey('Host', $request->headers);
        $this->assertEquals(['example.com'], $request->headers['Host']);
        $this->assertEquals('', $request->body);

        fclose($stream);
    }

    /**
     * Тест создания экземпляра HttpRequest со множеством заголовков и телом
     *
     * @throws HttpException
     */
    public function testCreateFromStreamWithMultipleHeaders(): void
    {
        $body = '{"name":"test","value":42}';
        $content = "POST /api/data?q=test&lang=en HTTP/1.1\r\n" .
            "Host: api.example.com\r\n" .
            "Accept: application/xml\r\n" .
            "Accept: application/json\r\n" .
            "Content-Length: " . strlen($body) . "\r\n" .
            "Content-Type: application/json\r\n" .
            "Authorization: Bearer token123\r\n" .
            "\r\n" .
            $body;

        $stream = $this->createStream($content);

        $request = $this->factory->createFromStream($stream, 8192);

        $this->assertEquals('POST', $request->method);
        $this->assertEquals('/api/data?q=test&lang=en', $request->path);
        $this->assertEquals('HTTP/1.1', $request->protocol);

        $this->assertCount(5, $request->headers);
        $this->assertEquals([strlen($body)], $request->headers['Content-Length']);
        $this->assertEquals(['application/json'], $request->headers['Content-Type']);
        $this->assertEquals(['Bearer token123'], $request->headers['Authorization']);

        $this->assertCount(2, $request->headers['Accept']);
        $this->assertEquals(['application/xml', 'application/json'], $request->headers['Accept']);

        $this->assertEquals($body, $request->body);

        fclose($stream);
    }

    /**
     * Тест создания экземпляра HttpRequest с заголовком Transfer-Encoding: chunked
     *
     * @throws HttpException
     */
    public function testCreateFromStreamWithChunkedEncoding(): void
    {
        $content = "POST /upload HTTP/1.1\r\n" .
            "Host: example.com\r\n" .
            "Transfer-Encoding: chunked\r\n" .
            "\r\n" .
            "5\r\n" .
            "Hello\r\n" .
            "6\r\n" .
            " World\r\n" .
            "0\r\n" .
            "\r\n";

        $stream = $this->createStream($content);

        $request = $this->factory->createFromStream($stream, 8192);

        $this->assertEquals('POST', $request->method);
        $this->assertEquals('Hello World', $request->body);

        fclose($stream);
    }

    /**
     * Тест создания экземпляра HttpRequest с заголовком Transfer-Encoding: chunked и метаданными
     *
     * @throws HttpException
     */
    public function testCreateFromStreamWithChunkedEncodingAndExtensions(): void
    {
        $content = "POST /upload HTTP/1.1\r\n" .
            "Host: example.com\r\n" .
            "Transfer-Encoding: chunked\r\n" .
            "\r\n" .
            "5;name=value\r\n" .
            "Hello\r\n" .
            "0\r\n" .
            "\r\n";

        $stream = $this->createStream($content);

        $request = $this->factory->createFromStream($stream, 8192);

        $this->assertEquals('Hello', $request->body);

        fclose($stream);
    }

    /**
     * Тест создания экземпляра HttpRequest с заголовком Transfer-Encoding: chunked и trailers
     *
     * @throws HttpException
     */
    public function testCreateFromStreamWithChunkedEncodingAndTrailers(): void
    {
        $content = "POST /upload HTTP/1.1\r\n" .
            "Host: example.com\r\n" .
            "Transfer-Encoding: chunked\r\n" .
            "\r\n" .
            "4\r\n" .
            "test\r\n" .
            "0\r\n" .
            "X-Trailer: value\r\n" .
            "\r\n";

        $stream = $this->createStream($content);

        $request = $this->factory->createFromStream($stream, 8192);

        $this->assertEquals('test', $request->body);

        fclose($stream);
    }

    /**
     * Тест создания экземпляра HttpRequest с заголовком Transfer-Encoding: chunked и HTTP/1.0
     *
     * @throws HttpException
     */
    public function testCreateFromStreamWithChunkedEncodingAndHTTP10(): void
    {
        $content = "POST /upload HTTP/1.0\r\n" .
            "Host: example.com\r\n" .
            "Transfer-Encoding: chunked\r\n" .
            "\r\n" .
            "5\r\n" .
            "Hello\r\n" .
            "6\r\n" .
            " World\r\n" .
            "0\r\n" .
            "\r\n";

        $stream = $this->createStream($content);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage(HttpException::CODES[501]);

        $this->factory->createFromStream($stream, 8192);

        fclose($stream);
    }

    /**
     * Тест создания экземпляра HttpRequest с бинарными данными
     *
     * @throws HttpException
     */
    public function testCreateFromStreamThrowsExceptionOnNonHttpProtocol(): void
    {
        $content = "\x00\x01\x02Invalid binary data\r\n";

        $stream = $this->createStream($content);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This is not the HTTP protocol');

        $this->factory->createFromStream($stream, 8192);

        fclose($stream);
    }

    /**
     * Тест создания экземпляра HttpRequest с очень длинным заголовком
     *
     * @throws HttpException
     */
    public function testCreateFromStreamThrowsExceptionOnRequestTooLarge(): void
    {
        $content = "GET / HTTP/1.1\r\n" .
            "Host: example.com\r\n" .
            "X-Large-Header: " . str_repeat('a', 9000) . "\r\n" .
            "\r\n";

        $stream = $this->createStream($content);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage(HttpException::CODES[431]);

        $this->factory->createFromStream($stream, 8192);

        fclose($stream);
    }

    /**
     * Тест создания экземпляра HttpRequest с превышением лимита длины тела в Content-Length
     *
     * @throws HttpException
     */
    public function testCreateFromStreamThrowsExceptionOnContentLengthExceedsLimit(): void
    {
        $content = "POST /data HTTP/1.1\r\n" .
            "Host: example.com\r\n" .
            "Content-Length: 10000\r\n" .
            "\r\n";

        $stream = $this->createStream($content);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage(HttpException::CODES[413]);

        $this->factory->createFromStream($stream, 8192);

        fclose($stream);
    }

    /**
     * Тест создания экземпляра HttpRequest с превышением лимита длины тела при chunked-кодировке
     *
     * @throws HttpException
     */
    public function testCreateFromStreamThrowsExceptionOnChunkedBodyExceedsLimit(): void
    {
        $content = "POST /upload HTTP/1.1\r\n" .
            "Host: example.com\r\n" .
            "Transfer-Encoding: chunked\r\n" .
            "\r\n" .
            "64\r\n" . // 100 bytes in hex
            str_repeat('a', 100) . "\r\n" .
            "0\r\n" .
            "\r\n";

        $stream = $this->createStream($content);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage(HttpException::CODES[413]);

        $this->factory->createFromStream($stream, 150); // Лимит меньше чем тело

        fclose($stream);
    }

    /**
     * Тест создания экземпляра HttpRequest с реальным превышением лимита длины тела
     *
     * @throws HttpException
     */
    public function testCreateFromStreamWithLargeBody(): void
    {
        $body = str_repeat('Large content block. ', 100);
        $content = "PUT /data HTTP/1.1\r\n" .
            "Host: example.com\r\n" .
            "Content-Length: " . strlen($body) . "\r\n" .
            "\r\n" .
            $body;

        $stream = $this->createStream($content);

        $request = $this->factory->createFromStream($stream, 8192);

        $this->assertEquals($body, $request->body);

        fclose($stream);
    }

    /**
     * Тест создания экземпляра HttpRequest с HTTP-запросом с LF-разделителями
     *
     * @throws HttpException
     */
    public function testCreateFromStreamWithLineFeedOnly(): void
    {
        $content = "GET / HTTP/1.1\n" .
            "Host: example.com\n" .
            "\n";

        $stream = $this->createStream($content);

        $request = $this->factory->createFromStream($stream, 8192);

        $this->assertEquals('GET', $request->method);
        $this->assertEquals('/', $request->path);
        $this->assertEquals('HTTP/1.1', $request->protocol);
        $this->assertArrayHasKey('Host', $request->headers);
        $this->assertEquals(['example.com'], $request->headers['Host']);
        $this->assertEquals('', $request->body);

        fclose($stream);
    }

    /**
     * Тест создания экземпляра HttpRequest с HTTP/1.0 запросом
     *
     * @throws HttpException
     */
    public function testCreateFromStreamWithHTTP10(): void
    {
        $content = "GET /old HTTP/1.0\r\n" .
            "Host: example.com\r\n" .
            "\r\n";

        $stream = $this->createStream($content);

        $request = $this->factory->createFromStream($stream, 8192);

        $this->assertEquals('HTTP/1.0', $request->protocol);
        $this->assertEquals('', $request->body); // HTTP/1.0 без chunked

        fclose($stream);
    }

    public function testCreateFromStreamWithEncodingGzip(): void
    {
        $content = "GET /old HTTP/1.0\r\n" .
            "Host: example.com\r\n" .
            "Transfer-Encoding: gzip\r\n" .
            "\r\n";

        $stream = $this->createStream($content);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage(HttpException::CODES[501]);

        $this->factory->createFromStream($stream, 8192);

        fclose($stream);
    }
}
