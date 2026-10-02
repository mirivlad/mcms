<?php

declare(strict_types=1);

namespace MCMS\Modules\Media\Api;

use CodeIgniter\HTTP\Files\UploadedFile;

interface MediaLibraryInterface
{
    public function list(string $query = '', int $limit = 100, int $offset = 0, bool $imagesOnly = false): array;

    public function count(string $query = '', bool $imagesOnly = false): int;

    public function find(int $id): ?array;

    public function store(
        UploadedFile $file,
        ?int $userId = null,
        ?string $title = null,
        ?string $altText = null,
    ): array;

    public function updateMetadata(int $id, ?string $title, ?string $altText): array;

    public function delete(int $id, bool $force = false): void;

    public function url(int|array $media): string;

    public function thumbnailUrl(int|array $media): string;

    public function supportedMimeTypes(): array;
}
