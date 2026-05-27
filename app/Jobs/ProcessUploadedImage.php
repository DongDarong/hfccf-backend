<?php

namespace App\Jobs;

use App\Services\ImageProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessUploadedImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public string $disk,
        public string $path,
        public array $options = [],
    ) {
        //
    }

    /**
     * Queue retries remain available for unexpected processing failures.
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(ImageProcessingService $service): void
    {
        $service->optimizeStoredImage($this->disk, $this->path, $this->options);
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('Queued image processing failed.', [
            'disk' => $this->disk,
            'path' => $this->path,
            'exception' => $exception->getMessage(),
        ]);
    }
}
