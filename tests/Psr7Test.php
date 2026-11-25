<?php

declare(strict_types=1);

namespace Kurama\Tests;

use Kurama\Http\RequestFactory;
use Kurama\Http\Response;
use Kurama\Http\ResponseFactory;
use Kurama\Http\ServerRequestFactory;
use Kurama\Http\Stream;
use Kurama\Http\StreamFactory;
use Kurama\Http\UploadedFile;
use Kurama\Http\UploadedFileFactory;
use Kurama\Http\Uri;
use Kurama\Http\UriFactory;
use PHPUnit\Framework\TestCase;

final class Psr7Test extends TestCase
{
    public function testStreamReadWriteSeek(): void
    {
        $stream = new Stream('hello');
        self::assertSame(5, $stream->getSize());
        self::assertSame('hello', (string) $stream);

        $stream->write(' world');
        self::assertSame(11, $stream->getSize());
        $stream->rewind();
        self::assertSame('hello world', $stream->getContents());
        $stream->close();
        self::assertNull($stream->getSize());
    }

    public function testUriComponentsAndImmutability(): void
    {
        $uri = new Uri('https://user:pass@example.com:8443/users/42?q=1#top');
        self::assertSame('https', $uri->getScheme());
        self::assertSame('user:pass@example.com:8443', $uri->getAuthority());
        self::assertSame('/users/42', $uri->getPath());
        self::assertSame('q=1', $uri->getQuery());
        self::assertSame('top', $uri->getFragment());

        $changed = $uri->withScheme('http')->withPort(null)->withQuery('page=2');
        self::assertSame('https', $uri->getScheme());
        self::assertSame('http://user:pass@example.com/users/42?page=2#top', (string) $changed);
    }

    public function testRequestHeadersAndImmutability(): void
    {
        $request = (new RequestFactory())->createRequest('get', '/users');
        self::assertSame('GET', $request->getMethod());
        self::assertSame('/users', $request->getRequestTarget());

        $withHeader = $request->withHeader('X-Test', 'one')
            ->withAddedHeader('X-Test', 'two');
        self::assertSame(['one', 'two'], $withHeader->getHeader('x-test'));
        self::assertSame('one, two', $withHeader->getHeaderLine('X-Test'));
        self::assertSame([], $request->getHeaders());

        $without = $withHeader->withoutHeader('X-Test');
        self::assertFalse($without->hasHeader('x-test'));
    }

    public function testServerRequestAttributesAndBody(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/echo', ['REMOTE_ADDR' => '127.0.0.1']);
        $request = $request->withParsedBody(['a' => 1])
            ->withQueryParams(['q' => 'x'])
            ->withAttribute('user', 'kurama');

        self::assertSame(['a' => 1], $request->getParsedBody());
        self::assertSame('kurama', $request->getAttribute('user'));
        self::assertSame(['user' => 'kurama'], $request->getAttributes());
        self::assertSame([], $request->withoutAttribute('user')->getAttributes());
        self::assertSame('127.0.0.1', $request->getServerParams()['REMOTE_ADDR']);
    }

    public function testResponseStatusAndFactories(): void
    {
        $response = (new ResponseFactory())->createResponse(404);
        self::assertSame(404, $response->getStatusCode());
        self::assertSame('Not Found', $response->getReasonPhrase());

        $custom = $response->withStatus(419)->withHeader('X-Test', 'yes');
        self::assertSame(419, $custom->getStatusCode());
        self::assertSame('Page Expired', $custom->getReasonPhrase());
        self::assertSame('yes', $custom->getHeaderLine('x-test'));
    }

    public function testStreamAndUriFactories(): void
    {
        self::assertSame('body', (string) (new StreamFactory())->createStream('body'));
        self::assertSame('/x', (string) (new UriFactory())->createUri('/x'));
    }

    public function testUploadedFileFactory(): void
    {
        $file = (new UploadedFileFactory())->createUploadedFile(
            (new StreamFactory())->createStream('data'),
            null,
            UPLOAD_ERR_OK,
            'note.txt',
            'text/plain',
        );

        self::assertSame(4, $file->getSize());
        self::assertSame('note.txt', $file->getClientFilename());
        self::assertSame('text/plain', $file->getClientMediaType());
        self::assertSame('data', (string) $file->getStream());
    }
}
