<?php

namespace Tests\Unit\Support;

use App\Jobs\ProcessUploadedImage;
use App\Support\ImageStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageStorageTest extends TestCase
{
    public function test_store_dispatches_image_processing_job_without_changing_return_shape(): void
    {
        Queue::fake();
        Storage::fake('public');

        config([
            'filesystems.image_disk' => 'public',
        ]);

        $file = UploadedFile::fake()->image('avatar.jpg', 320, 320);

        $path = ImageStorage::store($file, 'avatars');

        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);

        Queue::assertPushed(ProcessUploadedImage::class, function (ProcessUploadedImage $job) use ($path): bool {
            return $job->disk === 'public' && $job->path === $path;
        });
    }
}
