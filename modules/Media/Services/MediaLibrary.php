<?php

declare(strict_types=1);

namespace MCMS\Modules\Media\Services;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\Files\UploadedFile;
use InvalidArgumentException;
use MCMS\Modules\Media\Api\MediaLibraryInterface;
use RuntimeException;

final class MediaLibrary implements MediaLibraryInterface
{
    private const MIME_EXTENSIONS = [
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
        'image/gif'       => 'gif',
        'application/pdf' => 'pdf',
    ];

    public function __construct(
        private readonly BaseConnection $db,
        private readonly string $root,
    ) {
        if (! is_dir($this->root)
            && ! mkdir($this->root, 0775, true)
            && ! is_dir($this->root)
        ) {
            throw new RuntimeException('Unable to create Media storage directory.');
        }
    }

    public function list(string $query = '', int $limit = 100, int $offset = 0): array
    {
        $builder = $this->db->table('media_files');

        $query = trim($query);
        if ($query !== '') {
            $builder
                ->groupStart()
                ->like('original_name', $query)
                ->orLike('title', $query)
                ->orLike('alt_text', $query)
                ->groupEnd();
        }

        $rows = $builder
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->limit(max(1, min(250, $limit)), max(0, $offset))
            ->get()
            ->getResultArray();

        return array_map(fn (array $row): array => $this->decorate($row), $rows);
    }

    public function count(string $query = ''): int
    {
        $builder = $this->db->table('media_files');

        $query = trim($query);
        if ($query !== '') {
            $builder
                ->groupStart()
                ->like('original_name', $query)
                ->orLike('title', $query)
                ->orLike('alt_text', $query)
                ->groupEnd();
        }

        return $builder->countAllResults();
    }

    public function find(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $row = $this->db->table('media_files')
            ->where('id', $id)
            ->get()
            ->getRowArray();

        return $row === null ? null : $this->decorate($row);
    }

    public function store(
        UploadedFile $file,
        ?int $userId = null,
        ?string $title = null,
        ?string $altText = null,
    ): array {
        if (! $file->isValid() || $file->hasMoved()) {
            throw new RuntimeException($file->getErrorString() ?: 'Upload failed.');
        }

        $maxBytes = $this->maxUploadBytes();
        $size = (int) $file->getSize();

        if ($size <= 0) {
            throw new RuntimeException('The uploaded file is empty.');
        }

        if ($size > $maxBytes) {
            throw new RuntimeException(
                'The file exceeds the Media upload limit of ' . $this->maxUploadMb() . ' MB.'
            );
        }

        $tempPath = $file->getTempName();
        $mime = $this->detectMime($tempPath);

        if (! in_array($mime, $this->allowedMimeTypes(), true)) {
            throw new RuntimeException("File type {$mime} is not allowed.");
        }

        $extension = self::MIME_EXTENSIONS[$mime] ?? null;
        if ($extension === null) {
            throw new RuntimeException("Media does not know how to safely store {$mime}.");
        }

        $width = null;
        $height = null;

        if (str_starts_with($mime, 'image/')) {
            $imageInfo = @getimagesize($tempPath);
            if ($imageInfo === false) {
                throw new RuntimeException('The uploaded file could not be decoded as an image.');
            }

            $width = (int) $imageInfo[0];
            $height = (int) $imageInfo[1];

            if ($width <= 0 || $height <= 0) {
                throw new RuntimeException('The image dimensions are invalid.');
            }

            $megapixels = ($width * $height) / 1_000_000;
            if ($megapixels > $this->maxImageMegapixels()) {
                throw new RuntimeException(
                    'The image exceeds the Media pixel limit of '
                    . $this->maxImageMegapixels()
                    . ' megapixels.'
                );
            }
        }

        $relativeDirectory = date('Y/m');
        $directory = $this->root . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);

        if (! is_dir($directory)
            && ! mkdir($directory, 0775, true)
            && ! is_dir($directory)
        ) {
            throw new RuntimeException('Unable to create Media storage directory.');
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $storagePath = $relativeDirectory . '/' . $filename;
        $sha256 = hash_file('sha256', $tempPath);

        if (! is_string($sha256) || $sha256 === '') {
            throw new RuntimeException('Unable to fingerprint the uploaded file.');
        }

        $file->move($directory, $filename);

        $now = date('Y-m-d H:i:s');

        try {
            $this->db->table('media_files')->insert([
                'original_name' => $this->cleanOriginalName($file->getClientName()),
                'storage_path'  => $storagePath,
                'mime_type'     => $mime,
                'extension'     => $extension,
                'size_bytes'    => $size,
                'width'         => $width,
                'height'        => $height,
                'sha256'        => $sha256,
                'title'         => $this->cleanMetadata($title),
                'alt_text'      => $this->cleanMetadata($altText),
                'uploaded_by'   => $userId !== null && $userId > 0 ? $userId : null,
                'created_at'    => $now,
                'updated_at'    => null,
            ]);
        } catch (\Throwable $e) {
            @unlink($directory . DIRECTORY_SEPARATOR . $filename);
            throw $e;
        }

        $id = (int) $this->db->insertID();
        $stored = $this->find($id);

        if ($stored === null) {
            throw new RuntimeException('Media record was created but could not be reloaded.');
        }

        return $stored;
    }

    public function updateMetadata(int $id, ?string $title, ?string $altText): array
    {
        if ($this->find($id) === null) {
            throw new RuntimeException('Media item not found.');
        }

        $this->db->table('media_files')
            ->where('id', $id)
            ->update([
                'title'      => $this->cleanMetadata($title),
                'alt_text'   => $this->cleanMetadata($altText),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        $updated = $this->find($id);

        if ($updated === null) {
            throw new RuntimeException('Media item disappeared while updating metadata.');
        }

        return $updated;
    }

    public function delete(int $id): void
    {
        $media = $this->find($id);
        if ($media === null) {
            return;
        }

        $absolutePath = $this->absolutePath((string) $media['storage_path']);

        if (is_file($absolutePath) && ! unlink($absolutePath)) {
            throw new RuntimeException('Unable to delete the Media file from storage.');
        }

        $this->db->table('media_files')->where('id', $id)->delete();
    }

    public function url(int|array $media): string
    {
        if (is_int($media)) {
            $media = $this->find($media);
            if ($media === null) {
                throw new RuntimeException('Media item not found.');
            }
        }

        $path = (string) ($media['storage_path'] ?? '');
        if ($path === '') {
            throw new InvalidArgumentException('Media item has no storage path.');
        }

        return '/assets/uploads/media/' . implode(
            '/',
            array_map('rawurlencode', explode('/', $path)),
        );
    }

    public function supportedMimeTypes(): array
    {
        return self::MIME_EXTENSIONS;
    }

    private function decorate(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['size_bytes'] = (int) $row['size_bytes'];
        $row['width'] = $row['width'] === null ? null : (int) $row['width'];
        $row['height'] = $row['height'] === null ? null : (int) $row['height'];
        $row['uploaded_by'] = $row['uploaded_by'] === null ? null : (int) $row['uploaded_by'];
        $row['is_image'] = str_starts_with((string) $row['mime_type'], 'image/');
        $row['url'] = $this->url($row);

        return $row;
    }

    private function detectMime(string $path): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);

        if (! is_string($mime) || $mime === '') {
            throw new RuntimeException('Unable to determine the uploaded file type.');
        }

        return strtolower(trim($mime));
    }

    private function maxUploadMb(): int
    {
        $value = (int) (service('settings')->get('media.max_upload_mb') ?? 20);

        return max(1, min(64, $value));
    }

    private function maxUploadBytes(): int
    {
        return $this->maxUploadMb() * 1024 * 1024;
    }

    private function maxImageMegapixels(): int
    {
        $value = (int) (service('settings')->get('media.max_image_megapixels') ?? 40);

        return max(1, min(200, $value));
    }

    private function allowedMimeTypes(): array
    {
        $configured = service('settings')->get('media.allowed_mime_types');

        if (! is_array($configured) || $configured === []) {
            return array_keys(self::MIME_EXTENSIONS);
        }

        return array_values(array_intersect(
            array_keys(self::MIME_EXTENSIONS),
            array_filter($configured, 'is_string'),
        ));
    }

    private function absolutePath(string $relative): string
    {
        $relative = str_replace('\\', '/', trim($relative, '/'));

        if ($relative === '') {
            throw new InvalidArgumentException('Media storage path cannot be empty.');
        }

        foreach (explode('/', $relative) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new InvalidArgumentException('Unsafe Media storage path.');
            }
        }

        return rtrim($this->root, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    private function cleanOriginalName(string $name): string
    {
        $name = trim(str_replace(["\0", "\r", "\n"], '', $name));

        return mb_substr($name === '' ? 'upload' : $name, 0, 255);
    }

    private function cleanMetadata(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, 255);
    }
}
