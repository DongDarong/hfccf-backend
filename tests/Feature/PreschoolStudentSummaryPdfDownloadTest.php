<?php

namespace Tests\Feature;

use App\Models\PreschoolAcademicYear;
use App\Models\PreschoolAttendanceRecord;
use App\Models\PreschoolClass;
use App\Models\PreschoolStudent;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class PreschoolStudentSummaryPdfDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_student_summary_pdf_download_returns_backend_generated_pdf_attachment(): void
    {
        $context = $this->createReportContext();
        $this->fakePdfBrowserProcess();

        $response = $this->actingWithToken($context['admin'])->get('/api/preschool/reports/student-summary/download?'.http_build_query([
            'mode' => 'individual',
            'academic_year_id' => $context['year']->id,
            'class_id' => $context['class']->id,
            'student_id' => $context['student']->id,
        ]));

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename="StudentSummaryReport_Individual_'.now()->toDateString().'.pdf"');

        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
        Process::assertRan(fn ($process, $result): bool => collect($process->command)->contains(fn ($argument) => str_starts_with($argument, '--print-to-pdf=')));
        $this->assertSame([], File::files(storage_path('app/tmp/preschool-student-summary')));
    }

    public function test_student_summary_pdf_download_validates_required_filter_combinations(): void
    {
        $context = $this->createReportContext();

        $response = $this->actingWithToken($context['admin'])->getJson('/api/preschool/reports/student-summary/download?'.http_build_query([
            'mode' => 'individual',
            'class_id' => $context['class']->id,
        ]));

        $response->assertUnprocessable();
    }

    public function test_teacher_cannot_download_student_summary_pdf(): void
    {
        $context = $this->createReportContext();
        $teacher = User::factory()->asTeacherPreschool()->create();

        $this->actingWithToken($teacher)->getJson('/api/preschool/reports/student-summary/download?'.http_build_query([
            'mode' => 'class',
            'class_id' => $context['class']->id,
        ]))->assertForbidden();
    }

    public function test_student_summary_pdf_download_cleans_up_temp_files_when_browser_process_fails(): void
    {
        $context = $this->createReportContext();

        config()->set('services.preschool_pdf.browser_binary', PHP_BINARY);
        File::ensureDirectoryExists(storage_path('app/tmp/preschool-student-summary'));
        foreach (File::files(storage_path('app/tmp/preschool-student-summary')) as $file) {
            File::delete($file->getPathname());
        }

        Process::fake([
            '*' => Process::result('', 'simulated renderer failure', 1),
        ])->preventStrayProcesses();

        $response = $this->actingWithToken($context['admin'])->getJson('/api/preschool/reports/student-summary/download?'.http_build_query([
            'mode' => 'class',
            'class_id' => $context['class']->id,
        ]));

        $response->assertStatus(500)
            ->assertJsonPath('message', 'Student Summary PDF rendering is temporarily unavailable.');

        $this->assertSame([], File::files(storage_path('app/tmp/preschool-student-summary')));
    }

    /**
     * @return array{admin:User,year:PreschoolAcademicYear,class:PreschoolClass,student:PreschoolStudent}
     */
    private function createReportContext(): array
    {
        $admin = User::factory()->asAdminPreschool()->create();
        $year = PreschoolAcademicYear::factory()->create([
            'label' => 'Academic Year 2026',
        ]);
        $class = PreschoolClass::factory()->create([
            'name' => 'Sunflower',
        ]);
        $student = PreschoolStudent::factory()->create([
            'first_name' => 'មីយ៉ា',
            'last_name' => 'Lopez',
            'latin_name' => 'Mia Lopez',
            'student_code' => 'PS-QA-0001',
            'gender' => 'female',
            'nationality' => 'Cambodian',
        ]);

        DB::table('preschool_class_students')->insert([
            'class_id' => $class->id,
            'student_id' => $student->id,
            'enrolled_at' => now()->subMonths(2),
            'academic_year' => $year->label,
            'academic_year_id' => $year->id,
            'status' => 'active',
            'enrollment_status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        PreschoolAttendanceRecord::query()->create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'recorded_by_user_id' => $admin->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'present',
        ]);

        return compact('admin', 'year', 'class', 'student');
    }

    private function fakePdfBrowserProcess(): void
    {
        config()->set('services.preschool_pdf.browser_binary', PHP_BINARY);

        Process::fake(function ($process) {
            $pdfArgument = collect($process->command)->first(fn ($argument) => str_starts_with($argument, '--print-to-pdf='));
            if (is_string($pdfArgument)) {
                File::put(substr($pdfArgument, strlen('--print-to-pdf=')), "%PDF-1.4\nxref\nstartxref\n%%EOF");
            }

            return Process::result('', '', 0);
        })->preventStrayProcesses();
    }
}
