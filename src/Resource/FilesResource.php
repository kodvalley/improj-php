<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Resource;

use Generator;
use Kodvalley\Improj\Exception\ConfigurationException;
use Kodvalley\Improj\Exception\ImprojException;
use Kodvalley\Improj\Internal\Data;
use Kodvalley\Improj\Model\DownloadLink;
use Kodvalley\Improj\Model\ProjectFile;
use Kodvalley\Improj\Model\Upload;
use Kodvalley\Improj\Page;
use Kodvalley\Improj\RequestOptions;
use UnexpectedValueException;

/**
 * Project files (scopes files:read, files:write).
 */
final class FilesResource extends AbstractResource
{
    public const MAX_UPLOAD_BYTES = 16 * 1024 * 1024;

    /**
     * Files and folders you can see. parent_id lists a folder, or "root" for the top level.
     *
     * @param array{limit?: int, cursor?: string, parent_id?: int|'root'} $params
     *
     * @return Page<ProjectFile>
     */
    public function list(int|string $projectId, array $params = [], ?RequestOptions $options = null): Page
    {
        if (isset($params['parent_id']) && $params['parent_id'] !== 'root') {
            $params['parent_id'] = (int) self::id($params['parent_id'], 'parent_id');
        }

        return $this->page('/projects/' . self::id($projectId, 'projectId') . '/files', $params, ProjectFile::fromArray(...), $options);
    }

    /**
     * @param array{limit?: int, parent_id?: int|'root'} $params
     *
     * @return Generator<int, ProjectFile>
     */
    public function all(int|string $projectId, array $params = [], ?RequestOptions $options = null): Generator
    {
        return $this->list($projectId, $params, $options)->autoPaging();
    }

    public function get(int|string $projectId, int|string $fileId, ?RequestOptions $options = null): ProjectFile
    {
        return $this->getOne('/projects/' . self::id($projectId, 'projectId') . '/files/' . self::id($fileId, 'fileId'), ProjectFile::fromArray(...), $options);
    }

    /** A signed download link, valid for 5 minutes. Use it right away; do not store it. */
    public function downloadLink(int|string $projectId, int|string $fileId, ?RequestOptions $options = null): DownloadLink
    {
        return $this->getOne('/projects/' . self::id($projectId, 'projectId') . '/files/' . self::id($fileId, 'fileId') . '/download', DownloadLink::fromArray(...), $options);
    }

    /**
     * Step 1 of an upload: announce the file. Prefer upload(), which does both steps.
     *
     * @param array{name: string, size: int, mime_type: string, parent_id?: int|null} $input
     */
    public function createUpload(int|string $projectId, array $input, ?RequestOptions $options = null): Upload
    {
        return $this->write('POST', '/projects/' . self::id($projectId, 'projectId') . '/uploads', $input, Upload::fromArray(...), $options);
    }

    /**
     * Step 2 of an upload: send the bytes with an exact Content-Length. The
     * request always goes to <baseUrl>/uploads/<id>, never to a URL taken from
     * a response, so the token cannot be sent anywhere else. Upload ids are
     * single use: after a network failure, start again with createUpload().
     */
    public function sendUpload(Upload|string $upload, string $contents, ?RequestOptions $options = null): ProjectFile
    {
        $id = $upload instanceof Upload ? $upload->id : $upload;
        if (preg_match('/^upl_[A-Za-z0-9_-]{20,64}$/', $id) !== 1) {
            throw new ConfigurationException('Not a valid upload id.', 'CONFIGURATION');
        }
        if ($upload instanceof Upload && \strlen($contents) > $upload->maxBytes) {
            throw new ConfigurationException(sprintf('The data is %d bytes but the upload was announced with at most %d.', \strlen($contents), $upload->maxBytes), 'CONFIGURATION');
        }
        $opts = $options === null ? null : new RequestOptions(null, $options->maxRetries, $options->headers);
        $body = $this->transport->upload('/uploads/' . $id, $contents, $opts);
        try {
            if ($body === null || array_is_list($body)) {
                throw new UnexpectedValueException('The answer has no data object.');
            }

            return ProjectFile::fromArray(Data::object($body, 'data'));
        } catch (UnexpectedValueException $e) {
            throw new ImprojException('The answer is malformed: ' . $e->getMessage(), 'INVALID_RESPONSE', previous: $e);
        }
    }

    /**
     * Uploads file contents in both steps and returns the created file.
     *
     * @param string      $name     file name shown in IMPROJ, 1 to 255 characters
     * @param string      $contents the bytes
     * @param string|null $mimeType default application/octet-stream
     */
    public function upload(int|string $projectId, string $name, string $contents, ?string $mimeType = null, ?int $parentId = null, ?RequestOptions $options = null): ProjectFile
    {
        $size = \strlen($contents);
        if ($size < 1) {
            throw new ConfigurationException('Cannot upload an empty file.', 'CONFIGURATION');
        }
        if ($size > self::MAX_UPLOAD_BYTES) {
            throw new ConfigurationException('Files larger than 16 MB cannot be uploaded through the API.', 'CONFIGURATION');
        }
        $input = ['name' => $name, 'size' => $size, 'mime_type' => $mimeType ?? 'application/octet-stream'];
        if ($parentId !== null) {
            $input['parent_id'] = $parentId;
        }
        $announced = $this->createUpload($projectId, $input, $options);

        return $this->sendUpload($announced, $contents, $options);
    }

    /**
     * Uploads a local file. The MIME type is detected with ext-fileinfo when available.
     */
    public function uploadFile(int|string $projectId, string $path, ?string $name = null, ?string $mimeType = null, ?int $parentId = null, ?RequestOptions $options = null): ProjectFile
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new ConfigurationException('The file does not exist or is not readable.', 'CONFIGURATION');
        }
        $size = filesize($path);
        if ($size === false || $size > self::MAX_UPLOAD_BYTES) {
            throw new ConfigurationException('Files larger than 16 MB cannot be uploaded through the API.', 'CONFIGURATION');
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new ConfigurationException('The file could not be read.', 'CONFIGURATION');
        }
        if ($mimeType === null && \function_exists('mime_content_type')) {
            $detected = mime_content_type($path);
            $mimeType = \is_string($detected) && $detected !== '' ? $detected : null;
        }

        return $this->upload($projectId, $name ?? basename($path), $contents, $mimeType, $parentId, $options);
    }
}
