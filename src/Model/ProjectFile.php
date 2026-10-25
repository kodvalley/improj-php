<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Model;

use Kodvalley\Improj\Internal\Data;

/**
 * A file or folder of a project.
 */
final class ProjectFile extends Model
{
    public readonly int $id;

    public readonly int $projectId;

    public readonly string $name;

    public readonly string $type;

    public readonly bool $isFolder;

    public readonly ?int $parentId;

    public readonly ?string $mimeType;

    public readonly ?int $size;

    public readonly ?string $visibility;

    public readonly bool $downloadable;

    public readonly ?string $createdAt;

    public readonly ?string $updatedAt;

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(array $data)
    {
        parent::__construct($data);
        $this->id = Data::int($data, 'id');
        $this->projectId = Data::int($data, 'project_id');
        $this->name = Data::string($data, 'name');
        $this->type = Data::string($data, 'type');
        $this->isFolder = Data::bool($data, 'is_folder');
        $this->parentId = Data::intOrNull($data, 'parent_id');
        $this->mimeType = Data::stringOrNull($data, 'mime_type');
        $this->size = Data::intOrNull($data, 'size');
        $this->visibility = Data::stringOrNull($data, 'visibility');
        $this->downloadable = Data::bool($data, 'downloadable');
        $this->createdAt = Data::stringOrNull($data, 'created_at');
        $this->updatedAt = Data::stringOrNull($data, 'updated_at');
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws \UnexpectedValueException when a documented field is missing or has the wrong type
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }
}
