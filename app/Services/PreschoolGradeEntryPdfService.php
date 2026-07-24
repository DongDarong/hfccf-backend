<?php

namespace App\Services;

use App\Models\Organization;
use App\Support\ImageStorage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class PreschoolGradeEntryPdfService
{
    public function __construct(
        private readonly PreschoolGradeEntryReportService $reportService,
    ) {}

    /**
     * @param  array{academic_year_id:int,class_id:int,month:int,year:int}  $filters
     * @return array{filename:string,content:string}
     */
    public function export(array $filters): array
    {
        $filename = $this->filename((int) $filters['month'], (int) $filters['year']);
        $html = $this->renderHtml($filters);
        $content = $this->renderPdfHtmlWithBrowser($html);

        if ($content === '' || ! str_starts_with($content, '%PDF')) {
            throw new \RuntimeException('Grade Entry PDF rendering produced invalid content.');
        }

        return [
            'filename' => $filename,
            'content' => $content,
        ];
    }

    /**
     * @param  array{academic_year_id:int,class_id:int,month:int,year:int}  $filters
     */
    public function renderHtml(array $filters): string
    {
        $report = $this->reportService->monthly($filters);

        return view('pdf.preschool-grade-entry', [
            'class' => $report['class'],
            'academicYear' => $report['academicYear'],
            'month' => $report['month'],
            'year' => $report['year'],
            'submission' => $report['submission'],
            'students' => $report['students'],
            'generatedAt' => now(),
            'organization' => $this->organization(),
            'fontRegularPath' => $this->pdfFontFileUri('NotoSansKhmer-Regular.ttf'),
            'fontBoldPath' => $this->pdfFontFileUri('NotoSansKhmer-Bold.ttf'),
        ])->render();
    }

    /**
     * @return array{kh_name:string,en_name:string,details:array<int,string>,logo_data_uri:?string}
     */
    private function organization(): array
    {
        $organization = Organization::query()->where('is_active', true)->first();

        return [
            'kh_name' => $organization?->kh_name ?: 'អង្គការ HFCCF',
            'en_name' => $organization?->name ?: config('app.name', 'HFCCF'),
            'details' => array_values(array_filter([
                $organization?->address,
                $organization?->phone,
                $organization?->email,
            ])),
            'logo_data_uri' => $this->organizationLogoDataUri($organization?->logo),
        ];
    }

    private function organizationLogoDataUri(?string $logoPath): ?string
    {
        return $this->imagePathDataUri($logoPath) ?? $this->fileDataUri($this->officialOrganizationLogoPath());
    }

    private function imagePathDataUri(mixed $value): ?string
    {
        $path = ImageStorage::resolvePath($value);
        if ($path === null) {
            return null;
        }

        foreach (ImageStorage::deleteDisks() as $disk) {
            try {
                $storage = Storage::disk($disk);
                if ($storage->exists($path)) {
                    $mime = $storage->mimeType($path) ?: 'image/png';

                    return 'data:'.$mime.';base64,'.base64_encode($storage->get($path));
                }
            } catch (Throwable $exception) {
                Log::warning('Grade Entry PDF image lookup failed.', [
                    'disk' => $disk,
                    'path' => $path,
                    'exception' => $exception::class,
                ]);
            }
        }

        return null;
    }

    private function fileDataUri(?string $path): ?string
    {
        if (! $path || ! File::exists($path)) {
            return null;
        }

        $mime = File::mimeType($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode(File::get($path));
    }

    private function officialOrganizationLogoPath(): ?string
    {
        $path = public_path('images'.DIRECTORY_SEPARATOR.'hfccf-logo.png');

        return File::exists($path) ? $path : null;
    }

    private function filename(int $month, int $year): string
    {
        return sprintf('GradeEntry_%04d-%02d_%s.pdf', $year, $month, now()->toDateString());
    }

    private function renderPdfHtmlWithBrowser(string $html): string
    {
        $browserBinary = $this->resolvePdfBrowserBinary();
        $directory = storage_path('app/tmp/preschool-grade-entry');

        File::ensureDirectoryExists($directory);

        $token = (string) Str::uuid();
        $htmlPath = $directory.DIRECTORY_SEPARATOR.$token.'.html';
        $pdfPath = $directory.DIRECTORY_SEPARATOR.$token.'.pdf';
        $profilePath = $directory.DIRECTORY_SEPARATOR.$token.'-profile';

        try {
            File::put($htmlPath, $html);
            File::ensureDirectoryExists($profilePath);

            $result = $this->runPdfBrowserProcess($browserBinary, $htmlPath, $pdfPath, $profilePath);

            if ($result->failed() || ! File::exists($pdfPath)) {
                Log::error('Preschool Grade Entry PDF rendering failed.', [
                    'binary' => basename($browserBinary),
                    'exit_code' => $result->exitCode(),
                    'stderr' => $result->errorOutput(),
                    'stdout' => $result->output(),
                    'html_exists' => File::exists($htmlPath),
                    'pdf_exists' => File::exists($pdfPath),
                ]);

                throw new \RuntimeException('Grade Entry PDF rendering is temporarily unavailable.');
            }

            return (string) File::get($pdfPath);
        } catch (Throwable $exception) {
            Log::error('Failed to render Grade Entry PDF via browser engine.', [
                'binary' => basename($browserBinary),
                'exception_class' => $exception::class,
                'html_exists' => File::exists($htmlPath),
                'pdf_exists' => File::exists($pdfPath),
            ]);

            throw $exception;
        } finally {
            File::delete([$htmlPath, $pdfPath]);
            File::deleteDirectory($profilePath);
        }
    }

    protected function runPdfBrowserProcess(string $browserBinary, string $htmlPath, string $pdfPath, string $profilePath)
    {
        return Process::timeout(120)->run([
            $browserBinary,
            '--headless=new',
            '--disable-gpu',
            '--allow-file-access-from-files',
            '--no-pdf-header-footer',
            '--user-data-dir='.$profilePath,
            '--print-to-pdf='.$pdfPath,
            $this->filePathToUri($htmlPath),
        ]);
    }

    protected function resolvePdfBrowserBinary(): string
    {
        $configured = $this->normalizeConfiguredBrowserBinary(config('services.preschool_pdf.browser_binary'));
        if (is_string($configured) && $configured !== '') {
            if (File::exists($configured)) {
                return $configured;
            }

            Log::warning('Configured Preschool PDF browser binary was not found.', [
                'configured_binary' => $configured,
            ]);

            throw new \RuntimeException('Grade Entry PDF rendering is temporarily unavailable.');
        }

        $candidates = [
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
            '/usr/bin/google-chrome',
            '/usr/bin/chromium-browser',
            '/usr/bin/chromium',
        ];

        foreach ($candidates as $candidate) {
            if (File::exists($candidate)) {
                return $candidate;
            }
        }

        Log::error('No supported browser binary found for Grade Entry PDF rendering.');

        throw new \RuntimeException('Grade Entry PDF rendering is temporarily unavailable.');
    }

    private function normalizeConfiguredBrowserBinary(mixed $configured): ?string
    {
        if (! is_string($configured)) {
            return null;
        }

        $normalized = trim($configured);

        return $normalized === '' ? null : trim($normalized, " \t\n\r\0\x0B\"'");
    }

    private function pdfFontFileUri(string $filename): string
    {
        return $this->filePathToUri(resource_path('fonts'.DIRECTORY_SEPARATOR.$filename));
    }

    private function filePathToUri(string $path): string
    {
        $normalized = str_replace(DIRECTORY_SEPARATOR, '/', $path);

        if (preg_match('/^[A-Za-z]:\//', $normalized) === 1) {
            return 'file:///'.rawurlencode(substr($normalized, 0, 1)).':'.str_replace('%2F', '/', rawurlencode(substr($normalized, 2)));
        }

        return 'file://'.$normalized;
    }
}
