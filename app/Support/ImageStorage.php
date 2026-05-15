<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ImageStorage
{
    public static function diskName(): string
    {
        return (string) config('filesystems.image_disk', 'public');
    }

    /**
     * @return string[]
     */
    public static function deleteDisks(): array
    {
        $configuredDisk = self::diskName();
        $legacyDisks = config('filesystems.image_legacy_disks', ['public']);

        $disks = array_values(array_unique(array_filter(array_merge(
            [$configuredDisk],
            is_array($legacyDisks) ? $legacyDisks : [$legacyDisks],
        ))));

        return $disks === [] ? ['public'] : $disks;
    }

    public static function store(?UploadedFile $file, string $directory): ?string
    {
        if (! $file) {
            return null;
        }

        return $file->store($directory, self::diskName());
    }

    public static function url(mixed $value): ?string
    {
        $path = self::resolvePath($value);

        if ($path === null) {
            return null;
        }

        $rawValue = trim((string) $value);

        if ($rawValue !== '' && preg_match('/^https?:\/\//i', $rawValue) === 1) {
            return $rawValue;
        }

        foreach (self::deleteDisks() as $disk) {
            $storage = Storage::disk($disk);

            if (method_exists($storage, 'exists') && $storage->exists($path)) {
                return $storage->url($path);
            }
        }

        return Storage::disk(self::diskName())->url($path);
    }

    public static function delete(mixed $value): void
    {
        $path = self::resolvePath($value);

        if ($path === null) {
            return;
        }

        foreach (self::deleteDisks() as $disk) {
            Storage::disk($disk)->delete($path);
        }
    }

    public static function resolvePath(mixed $value): ?string
    {
        $rawValue = trim((string) $value);

        if ($rawValue === '' || str_starts_with($rawValue, 'blob:')) {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $rawValue) === 1) {
            $path = self::resolveManagedUrlPath($rawValue);

            return $path !== '' ? self::normalizePath($path) : null;
        }

        return self::normalizePath($rawValue);
    }

    private static function resolveManagedUrlPath(string $value): string
    {
        $normalizedValue = rtrim($value, '/');

        foreach (self::managedUrlBases() as $base) {
            if ($base === '' || ! str_starts_with($normalizedValue, $base)) {
                continue;
            }

            return ltrim(substr($normalizedValue, strlen($base)), '/');
        }

        return '';
    }

    /**
     * @return string[]
     */
    private static function managedUrlBases(): array
    {
        $bases = [];

        foreach (self::deleteDisks() as $disk) {
            $url = trim((string) config("filesystems.disks.{$disk}.url", ''));

            if ($url !== '') {
                $bases[] = rtrim($url, '/');
            }
        }

        return array_values(array_unique(array_filter($bases)));
    }

    private static function normalizePath(string $value): ?string
    {
        $path = ltrim(trim($value), '/');

        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        return $path !== '' ? $path : null;
    }
}
