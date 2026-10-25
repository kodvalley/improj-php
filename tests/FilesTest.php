<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Tests;

use Kodvalley\Improj\Exception\ConfigurationException;
use Kodvalley\Improj\Exception\ConnectionException;
use Kodvalley\Improj\Exception\ImprojException;
use Kodvalley\Improj\Model\Upload;
use Kodvalley\Improj\RequestOptions;
use Kodvalley\Improj\Resource\FilesResource;
use Kodvalley\Improj\Tests\Support\MockClient;
use PHPUnit\Framework\TestCase;

final class FilesTest extends TestCase
{
    private const ID = 'upl_ABCDEFGHIJKLMNOPQRSTUVWXYZ012345';

    /**
     * @return array<string, mixed>
     */
    private static function upload(int $max = 3): array
    {
        return ['id' => self::ID, 'upload_url' => 'https://evil.example.com/steal', 'method' => 'PUT', 'max_bytes' => $max, 'expires_at' => '2026-10-24T10:15:00Z'];
    }

    /**
     * @return array<string, mixed>
     */
    private static function file(): array
    {
        return ['id' => 77, 'project_id' => 1, 'name' => 'a.pdf', 'type' => 'upload', 'is_folder' => false, 'parent_id' => 5, 'mime_type' => 'application/pdf', 'size' => 3, 'visibility' => null, 'downloadable' => true, 'created_at' => null, 'updated_at' => null];
    }

    public function testUploadsInTwoStepsToTheBaseUrlNeverToUploadUrl(): void
    {
        $m = new MockClient(MockClient::json(201, ['data' => self::upload()]), MockClient::json(201, ['data' => self::file()]));
        $file = $m->client()->files->upload(1, 'a.pdf', 'abc', 'application/pdf', 5);
        self::assertSame(77, $file->id);

        [$announce, $send] = $m->requests;
        self::assertSame('POST', $announce->getMethod());
        self::assertSame('{"name":"a.pdf","size":3,"mime_type":"application/pdf","parent_id":5}', (string) $announce->getBody());
        self::assertNotSame('', $announce->getHeaderLine('Idempotency-Key'));

        self::assertSame('PUT', $send->getMethod());
        self::assertSame('https://improj.com/api/v1/uploads/' . self::ID, (string) $send->getUri());
        self::assertSame('application/octet-stream', $send->getHeaderLine('Content-Type'));
        self::assertSame('3', $send->getHeaderLine('Content-Length'));
        self::assertSame('Bearer ' . MockClient::TOKEN, $send->getHeaderLine('Authorization'));
        self::assertFalse($send->hasHeader('Idempotency-Key'));
        self::assertSame('abc', (string) $send->getBody());
    }

    public function testRefusesEmptyOversizedAndMismatchedData(): void
    {
        $m = new MockClient(MockClient::json(201, ['data' => self::file()]));
        $files = $m->client()->files;
        foreach ([
            static fn() => $files->upload(1, 'e', ''),
            static fn() => $files->upload(1, 'big', str_repeat('x', FilesResource::MAX_UPLOAD_BYTES + 1)),
            static fn() => $files->sendUpload(Upload::fromArray(self::upload(3)), 'abcd'),
            static fn() => $files->sendUpload('upl_../../me', 'a'),
        ] as $call) {
            try {
                $call();
                self::fail('accepted');
            } catch (ConfigurationException) {
                self::addToAssertionCount(1);
            }
        }
        self::assertSame([], $m->requests);
    }

    public function testPutIsRetriedOn503ButNotAfterANetworkError(): void
    {
        $m = new MockClient(MockClient::problem(503, 'SERVER_BUSY', ['Retry-After' => '0']), MockClient::json(201, ['data' => self::file()]));
        self::assertSame(77, $m->client()->files->sendUpload(self::ID, 'abc', new RequestOptions(idempotencyKey: 'ignored-for-put'))->id);
        self::assertCount(2, $m->requests);

        $m = new MockClient(MockClient::networkFailure(), MockClient::json(201, ['data' => self::file()]));
        $this->expectException(ConnectionException::class);
        try {
            $m->client()->files->sendUpload(self::ID, 'abc');
        } finally {
            self::assertCount(1, $m->requests);
        }
    }

    public function testMalformedUploadAnswer(): void
    {
        $m = new MockClient(MockClient::json(201, ['nope' => 1]));
        $this->expectException(ImprojException::class);
        $m->client()->files->sendUpload(self::ID, 'abc');
    }

    public function testUploadsALocalFileWithItsNameAndType(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'improj');
        self::assertIsString($path);
        file_put_contents($path, 'hello');
        try {
            $m = new MockClient(MockClient::json(201, ['data' => self::upload(5)]), MockClient::json(201, ['data' => self::file()]));
            $m->client()->files->uploadFile(1, $path, 'hello.txt');
            $announced = json_decode((string) $m->requests[0]->getBody(), true, 8, \JSON_THROW_ON_ERROR);
            self::assertIsArray($announced);
            self::assertSame('hello.txt', $announced['name']);
            self::assertSame(5, $announced['size']);
            self::assertSame('text/plain', $announced['mime_type']);
        } finally {
            unlink($path);
        }
        $this->expectException(ConfigurationException::class);
        (new MockClient())->client()->files->uploadFile(1, __DIR__ . '/does-not-exist.txt');
    }
}
