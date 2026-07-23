<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\PreschoolAcademicYear;
use App\Models\PreschoolAttendanceRecord;
use App\Models\PreschoolClass;
use App\Models\PreschoolStudent;
use App\Support\ImageStorage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class PreschoolStudentSummaryPdfService
{
    /**
     * @param  array{mode:string,academic_year_id?:int|null,class_id:int,student_id?:int|null}  $filters
     * @return array{filename:string,content:string}
     */
    public function export(array $filters): array
    {
        $mode = $filters['mode'];
        $filename = $this->filename($mode);
        $html = $this->renderHtml($filters);
        $content = $this->renderPdfHtmlWithBrowser($html);

        if ($content === '' || ! str_starts_with($content, '%PDF')) {
            throw new \RuntimeException('Student Summary PDF rendering produced invalid content.');
        }

        return [
            'filename' => $filename,
            'content' => $content,
        ];
    }

    /**
     * @param  array{mode:string,academic_year_id?:int|null,class_id:int,student_id?:int|null}  $filters
     */
    public function renderHtml(array $filters): string
    {
        $mode = $filters['mode'];
        $class = PreschoolClass::query()->findOrFail($filters['class_id']);
        $academicYear = ! empty($filters['academic_year_id'])
            ? PreschoolAcademicYear::query()->find($filters['academic_year_id'])
            : null;

        $payload = $mode === 'individual'
            ? $this->individualPayload((int) $filters['student_id'], $class)
            : $this->classPayload($class);

        return view('pdf.preschool-student-summary', [
            'mode' => $mode,
            'class' => $class,
            'academicYear' => $academicYear,
            'generatedAt' => now(),
            'organization' => $this->organization(),
            'fontRegularPath' => $this->pdfFontFileUri('NotoSansKhmer-Regular.ttf'),
            'fontBoldPath' => $this->pdfFontFileUri('NotoSansKhmer-Bold.ttf'),
            ...$payload,
        ])->render();
    }

    /**
     * @return array{student:PreschoolStudent,attendance:array<string,int>,studentPhoto:?string}
     */
    private function individualPayload(int $studentId, PreschoolClass $class): array
    {
        $student = PreschoolStudent::query()
            ->with(['classes'])
            ->findOrFail($studentId);

        $attendance = $this->attendanceSummary($student->id, $class->id);

        return [
            'student' => $student,
            'attendance' => $attendance,
            'studentPhoto' => $this->studentPhotoDataUri($student),
        ];
    }

    /**
     * @return array{classStudents:array<int,array<string,mixed>>,classSummary:array<string,int|string|null>}
     */
    private function classPayload(PreschoolClass $class): array
    {
        $students = PreschoolStudent::query()
            ->with(['classes'])
            ->whereHas('classes', static function ($query) use ($class): void {
                $query->where('preschool_classes.id', $class->id);
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $classStudents = $students->map(function (PreschoolStudent $student) use ($class): array {
            $attendance = $this->attendanceSummary($student->id, $class->id);

            return [
                'student' => $student,
                'attendancePercentage' => $attendance['percentage'],
                'studentPhoto' => $this->studentPhotoDataUri($student),
                'latestAssessment' => null,
            ];
        })->all();

        $attendancePercentages = collect($classStudents)
            ->pluck('attendancePercentage')
            ->filter(static fn ($value): bool => $value !== null);

        return [
            'classStudents' => $classStudents,
            'classSummary' => [
                'totalStudents' => count($classStudents),
                'activeStudents' => collect($classStudents)
                    ->filter(static fn (array $item): bool => ($item['student']->status ?? '') === 'active')
                    ->count(),
                'averageAttendance' => $attendancePercentages->isNotEmpty()
                    ? (int) round($attendancePercentages->average())
                    : 0,
                'averageAssessment' => null,
            ],
        ];
    }

    /**
     * @return array{present:int,absent:int,late:int,excused:int,total:int,percentage:int}
     */
    private function attendanceSummary(int $studentId, int $classId): array
    {
        $records = PreschoolAttendanceRecord::query()
            ->where('student_id', $studentId)
            ->where('class_id', $classId)
            ->get();

        $total = $records->count();
        $present = $records->where('status', 'present')->count();

        return [
            'present' => $present,
            'absent' => $records->where('status', 'absent')->count(),
            'late' => $records->where('status', 'late')->count(),
            'excused' => $records->where('status', 'excused')->count(),
            'total' => $total,
            'percentage' => $total > 0 ? (int) round(($present / $total) * 100) : 0,
        ];
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

    private function studentPhotoDataUri(PreschoolStudent $student): ?string
    {
        return $this->imagePathDataUri($student->avatar);
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
                Log::warning('Student Summary PDF image lookup failed.', [
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

    private function filename(string $mode): string
    {
        $label = $mode === 'individual' ? 'Individual' : 'Class';

        return sprintf('StudentSummaryReport_%s_%s.pdf', $label, now()->toDateString());
    }

    private function renderPdfHtmlWithBrowser(string $html): string
    {
        $browserBinary = $this->resolvePdfBrowserBinary();
        $directory = storage_path('app/tmp/preschool-student-summary');

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
                Log::error('Preschool Student Summary PDF rendering failed.', [
                    'binary' => basename($browserBinary),
                    'exit_code' => $result->exitCode(),
                    'stderr' => $result->errorOutput(),
                    'stdout' => $result->output(),
                    'html_exists' => File::exists($htmlPath),
                    'pdf_exists' => File::exists($pdfPath),
                ]);

                throw new \RuntimeException('Student Summary PDF rendering is temporarily unavailable.');
            }

            return (string) File::get($pdfPath);
        } catch (Throwable $exception) {
            Log::error('Failed to render Student Summary PDF via browser engine.', [
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

            throw new \RuntimeException('Student Summary PDF rendering is temporarily unavailable.');
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

        Log::error('No supported browser binary found for Student Summary PDF rendering.');

        throw new \RuntimeException('Student Summary PDF rendering is temporarily unavailable.');
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
