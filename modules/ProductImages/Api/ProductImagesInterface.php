<?php

declare(strict_types=1);

namespace MCMS\Modules\ProductImages\Api;

interface ProductImagesInterface
{
    public function createSet(int $userId): string;
    public function authorize(string $setId, int $userId): void;
    public function attach(string $setId, int $userId): void;
    public function images(string $setId): array;
    public function updatedAt(string $setId): string;
    public function upload(string $setId, string $file, string $name, ?int $replaceId = null): array;
    public function crop(string $setId, int $imageId, array $recipe): array;
    public function arrange(string $setId, array $ids, int $mainId): void;
    public function remove(string $setId, int $imageId): void;
    public function deleteSet(string $setId): void;
    /** Returns path/mime for an allowlisted derivative or an authenticated editor original. */
    public function file(string $setId, int $imageId, string $size): ?array;
}
