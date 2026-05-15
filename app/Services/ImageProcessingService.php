<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ImageProcessingService
{
    private const DEFAULT_MAX_WIDTH = 512;

    private const DEFAULT_MAX_HEIGHT = 512;

    private const DEFAULT_QUALITY = 84;

    private const DEFAULT_MIN_SIZE_TO_COMPRESS = 131072;

    /**
     * @param  array<string, mixed>  $options
     */
    public function optimizeStoredImage(string $disk, string $path, array $options = []): bool
    {
        $storage = Storage::disk($disk);

        if (! $storage->exists($path)) {
            return false;
        }

        $contents = $storage->get($path);

        if ($contents === '') {
            return false;
        }

        $originalSize = strlen($contents);
        $imageInfo = @getimagesizefromstring($contents);

        if (! is_array($imageInfo) || ! isset($imageInfo[0], $imageInfo[1], $imageInfo['mime'])) {
            return false;
        }

        $mimeType = strtolower((string) $imageInfo['mime']);

        if (! in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return false;
        }

        $width = max(1, (int) $imageInfo[0]);
        $height = max(1, (int) $imageInfo[1]);
        $maxWidth = max(1, (int) ($options['maxWidth'] ?? self::DEFAULT_MAX_WIDTH));
        $maxHeight = max(1, (int) ($options['maxHeight'] ?? self::DEFAULT_MAX_HEIGHT));
        $quality = min(100, max(1, (int) ($options['quality'] ?? self::DEFAULT_QUALITY)));
        $minSizeToCompress = max(0, (int) ($options['minSizeToCompress'] ?? self::DEFAULT_MIN_SIZE_TO_COMPRESS));

        $shouldResize = $width > $maxWidth || $height > $maxHeight;
        $shouldReencode = $shouldResize || $originalSize >= $minSizeToCompress;

        if (! $shouldReencode) {
            return false;
        }

        $source = $this->createImageFromString($contents);

        if (! $source) {
            return false;
        }

        try {
            $targetWidth = $shouldResize ? $this->scaledWidth($width, $height, $maxWidth, $maxHeight) : $width;
            $targetHeight = $shouldResize ? $this->scaledHeight($width, $height, $maxWidth, $maxHeight) : $height;
            $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

            if ($this->preservesTransparency($mimeType)) {
                imagealphablending($canvas, false);
                imagesavealpha($canvas, true);
                $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
                imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $transparent);
            }

            imagecopyresampled(
                $canvas,
                $source,
                0,
                0,
                0,
                0,
                $targetWidth,
                $targetHeight,
                $width,
                $height
            );

            $encoded = $this->encodeImage($canvas, $mimeType, $quality);

            if ($encoded === null) {
                imagedestroy($canvas);

                return false;
            }

            $optimizedSize = strlen($encoded);

            if ($optimizedSize >= $originalSize) {
                imagedestroy($canvas);

                return false;
            }

            $written = $storage->put($path, $encoded);

            imagedestroy($canvas);

            if (! $written) {
                throw new RuntimeException("Failed to write optimized image to [{$disk}]: {$path}");
            }

            return true;
        } finally {
            imagedestroy($source);
        }
    }

    private function createImageFromString(string $contents): mixed
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        return @imagecreatefromstring($contents) ?: null;
    }

    private function encodeImage(mixed $image, string $mimeType, int $quality): ?string
    {
        if (! is_resource($image) && ! ($image instanceof \GdImage)) {
            return null;
        }

        ob_start();

        $success = match ($mimeType) {
            'image/jpeg' => imagejpeg($image, null, $quality),
            'image/png' => imagepng($image, null, $this->pngCompressionLevel($quality)),
            'image/webp' => function_exists('imagewebp') ? imagewebp($image, null, $quality) : false,
            default => false,
        };

        $encoded = ob_get_clean();

        if (! $success || $encoded === false) {
            return null;
        }

        return $encoded;
    }

    private function pngCompressionLevel(int $quality): int
    {
        $quality = min(100, max(1, $quality));

        return (int) round((100 - $quality) / 11);
    }

    private function scaledWidth(int $width, int $height, int $maxWidth, int $maxHeight): int
    {
        $ratio = min($maxWidth / $width, $maxHeight / $height);

        return max(1, (int) round($width * $ratio));
    }

    private function scaledHeight(int $width, int $height, int $maxWidth, int $maxHeight): int
    {
        $ratio = min($maxWidth / $width, $maxHeight / $height);

        return max(1, (int) round($height * $ratio));
    }

    private function preservesTransparency(string $mimeType): bool
    {
        return in_array($mimeType, ['image/png', 'image/webp'], true);
    }
}
