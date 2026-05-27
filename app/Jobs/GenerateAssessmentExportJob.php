<?php

namespace App\Jobs;

use App\Models\AssessmentExportLog;
use App\Services\AssessmentExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateAssessmentExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public int $exportLogId)
    {
        //
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(AssessmentExportService $service): void
    {
        $exportLog = AssessmentExportLog::query()->findOrFail($this->exportLogId);
        $service->generate($exportLog);
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('Queued assessment export failed.', [
            'export_log_id' => $this->exportLogId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
