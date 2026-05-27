<?php

namespace Tests\Unit\Services;

use App\Services\ImageProcessingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageProcessingServiceTest extends TestCase
{
    public function test_it_recompresses_large_jpeg_images_in_place(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('large.jpg', 2400, 1600);
        $path = Storage::disk('public')->putFileAs('avatars', $file, 'large.jpg');

        $originalSize = Storage::disk('public')->size($path);
        $service = new ImageProcessingService;

        $processed = $service->optimizeStoredImage('public', $path);

        $this->assertTrue($processed);
        $this->assertTrue(Storage::disk('public')->exists($path));
        $this->assertLessThan($originalSize, Storage::disk('public')->size($path));
    }

    public function test_it_preserves_transparent_png_images(): void
    {
        Storage::fake('public');

        $path = 'avatars/transparent.png';
        $contents = $this->transparentPngContents();

        Storage::disk('public')->put($path, $contents);

        $service = new ImageProcessingService;

        $processed = $service->optimizeStoredImage('public', $path, [
            'maxWidth' => 16,
            'maxHeight' => 16,
            'minSizeToCompress' => 0,
            'quality' => 80,
        ]);

        $this->assertTrue($processed);

        $optimizedContents = Storage::disk('public')->get($path);
        $image = imagecreatefromstring($optimizedContents);
        $imageInfo = getimagesizefromstring($optimizedContents);

        $this->assertNotFalse($image);
        $this->assertIsArray($imageInfo);
        $this->assertSame('image/png', $imageInfo['mime'] ?? null);

        $rgba = imagecolorat($image, 0, 0);
        $alpha = ($rgba & 0x7F000000) >> 24;

        $this->assertSame(127, $alpha);

        imagedestroy($image);
    }

    public function test_it_returns_false_for_missing_files_without_throwing(): void
    {
        Storage::fake('public');

        $service = new ImageProcessingService;

        $this->assertFalse($service->optimizeStoredImage('public', 'avatars/missing.png'));
    }

    private function transparentPngContents(): string
    {
        $image = imagecreatetruecolor(32, 32);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);

        ob_start();
        imagepng($image);
        $contents = (string) ob_get_clean();

        imagedestroy($image);

        return $contents;
    }
}
