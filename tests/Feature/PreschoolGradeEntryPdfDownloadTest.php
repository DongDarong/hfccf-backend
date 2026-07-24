<?php

namespace Tests\Feature;

use App\Models\PreschoolAcademicYear;
use App\Models\PreschoolAssessmentCategory;
use App\Models\PreschoolClass;
use App\Models\PreschoolMonthlySubmission;
use App\Models\PreschoolStudent;
use App\Models\PreschoolStudentAssessment;
use App\Models\User;
use App\Services\PreschoolGradeEntryPdfService;
use App\Support\PreschoolMonthlySubmissionStatus;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class PreschoolGradeEntryPdfDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_grade_entry_pdf_download_returns_backend_generated_pdf_attachment(): void
    {
        $context = $this->createReportContext();
        $this->fakePdfBrowserProcess();

        $response = $this->actingWithToken($context['admin'])->get('/api/preschool/grades/download?'.http_build_query([
            'academic_year_id' => $context['year']->id,
            'class_id' => $context['class']->id,
            'month' => 7,
            'year' => 2026,
        ]));

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename="GradeEntry_2026-07_'.now()->toDateString().'.pdf"');

        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
        Process::assertRan(fn ($process, $result): bool => collect($process->command)->contains(fn ($argument) => str_starts_with($argument, '--print-to-pdf=')));
        $this->assertSame([], File::files(storage_path('app/tmp/preschool-grade-entry')));
    }

    public function test_grade_entry_pdf_html_uses_selected_class_academic_year_and_month_data(): void
    {
        $context = $this->createReportContext();

        $html = app(PreschoolGradeEntryPdfService::class)->renderHtml([
            'academic_year_id' => $context['year']->id,
            'class_id' => $context['class']->id,
            'month' => 7,
            'year' => 2026,
        ]);

        $this->assertStringContainsString('បញ្ជីពិន្ទុសិស្សប្រចាំខែ', $html);
        $this->assertStringContainsString('ថ្នាក់៖ Lotus', $html);
        $this->assertStringContainsString('ឆ្នាំសិក្សា៖ Academic Year 2026', $html);
        $this->assertStringContainsString('ខែ៖ កក្កដា ឆ្នាំ៖ 2026', $html);
        $this->assertStringContainsString('អត្តលេខសិស្ស', $html);
        $this->assertStringContainsString('គោត្តនាម-នាម', $html);
        $this->assertStringContainsString('ថ្ងៃខែឆ្នាំកំណើត', $html);
        $this->assertStringContainsString('PS-GRADE-0001', $html);
        $this->assertStringContainsString('សុភា រ៉ា', $html);
        $this->assertStringContainsString('>0<', $html);
        $this->assertStringContainsString('A', $html);
        $this->assertStringContainsString('PS-GRADE-0002', $html);
        $this->assertStringContainsString('សុខា Chan', $html);
        $this->assertStringNotContainsString('PS-OTHER-YEAR', $html);
        $this->assertStringNotContainsString('PS-INACTIVE', $html);
        $this->assertStringNotContainsString('html2pdf', $html);
    }

    public function test_grade_entry_pdf_validates_required_filters(): void
    {
        $context = $this->createReportContext();

        $this->actingWithToken($context['admin'])->getJson('/api/preschool/grades/download?'.http_build_query([
            'class_id' => $context['class']->id,
            'month' => 7,
            'year' => 2026,
        ]))->assertUnprocessable();
    }

    public function test_teacher_cannot_download_grade_entry_pdf(): void
    {
        $context = $this->createReportContext();
        $teacher = User::factory()->asTeacherPreschool()->create();

        $this->actingWithToken($teacher)->getJson('/api/preschool/grades/download?'.http_build_query([
            'academic_year_id' => $context['year']->id,
            'class_id' => $context['class']->id,
            'month' => 7,
            'year' => 2026,
        ]))->assertForbidden();
    }

    public function test_grade_entry_pdf_download_cleans_up_temp_files_when_browser_process_fails(): void
    {
        $context = $this->createReportContext();

        config()->set('services.preschool_pdf.browser_binary', PHP_BINARY);
        File::ensureDirectoryExists(storage_path('app/tmp/preschool-grade-entry'));
        foreach (File::files(storage_path('app/tmp/preschool-grade-entry')) as $file) {
            File::delete($file->getPathname());
        }

        Process::fake([
            '*' => Process::result('', 'simulated renderer failure', 1),
        ])->preventStrayProcesses();

        $response = $this->actingWithToken($context['admin'])->getJson('/api/preschool/grades/download?'.http_build_query([
            'academic_year_id' => $context['year']->id,
            'class_id' => $context['class']->id,
            'month' => 7,
            'year' => 2026,
        ]));

        $response->assertStatus(500)
            ->assertJsonPath('message', 'Grade Entry PDF rendering is temporarily unavailable.');

        $this->assertSame([], File::files(storage_path('app/tmp/preschool-grade-entry')));
    }

    /**
     * @return array{admin:User,year:PreschoolAcademicYear,class:PreschoolClass}
     */
    private function createReportContext(): array
    {
        $admin = User::factory()->asAdminPreschool()->create();
        $year = PreschoolAcademicYear::factory()->create([
            'label' => 'Academic Year 2026',
        ]);
        $otherYear = PreschoolAcademicYear::factory()->create([
            'label' => 'Academic Year 2025',
        ]);
        $class = PreschoolClass::factory()->create([
            'name' => 'Lotus',
        ]);
        $category = PreschoolAssessmentCategory::factory()->active()->create();

        $studentOne = $this->createStudent('PS-GRADE-0001', 'សុភា', 'រ៉ា', 'female');
        $studentTwo = $this->createStudent('PS-GRADE-0002', 'សុខា', 'Chan', 'male');
        $otherYearStudent = $this->createStudent('PS-OTHER-YEAR', 'Other', 'Year', 'female');
        $inactiveStudent = $this->createStudent('PS-INACTIVE', 'Inactive', 'Student', 'female');

        $this->attachStudent($class->id, $studentOne->id, $year->id, 'active', 'active');
        $this->attachStudent($class->id, $studentTwo->id, null, 'active', 'active', $year->label);
        $this->attachStudent($class->id, $otherYearStudent->id, $otherYear->id, 'active', 'active');
        $this->attachStudent($class->id, $inactiveStudent->id, $year->id, 'inactive', 'inactive');

        $submission = PreschoolMonthlySubmission::factory()
            ->for($year, 'academicYear')
            ->for($class, 'class')
            ->for($category, 'category')
            ->create([
                'submission_month' => '2026-07-01',
                'status' => PreschoolMonthlySubmissionStatus::DRAFT,
            ]);

        $this->createAssessment($submission, $studentOne, $class, $category, $year, $admin, 0, 'A');
        $this->createAssessment($submission, $studentTwo, $class, $category, $year, $admin, 85, 'B');

        return compact('admin', 'year', 'class');
    }

    private function createStudent(string $code, string $firstName, string $lastName, string $gender): PreschoolStudent
    {
        return PreschoolStudent::factory()->create([
            'student_code' => $code,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'gender' => $gender,
            'date_of_birth' => '2020-01-15',
            'status' => 'active',
        ]);
    }

    private function attachStudent(int $classId, int $studentId, ?int $academicYearId, string $status, string $enrollmentStatus, ?string $academicYear = null): void
    {
        DB::table('preschool_class_students')->insert([
            'class_id' => $classId,
            'student_id' => $studentId,
            'enrolled_at' => '2026-06-01 00:00:00',
            'academic_year_id' => $academicYearId,
            'academic_year' => $academicYear,
            'status' => $status,
            'enrollment_status' => $enrollmentStatus,
            'enrollment_started_at' => '2026-06-01 00:00:00',
            'enrollment_ended_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createAssessment(
        PreschoolMonthlySubmission $submission,
        PreschoolStudent $student,
        PreschoolClass $class,
        PreschoolAssessmentCategory $category,
        PreschoolAcademicYear $year,
        User $admin,
        int $score,
        string $rating,
    ): void {
        PreschoolStudentAssessment::query()->create([
            'monthly_submission_id' => $submission->id,
            'student_id' => $student->id,
            'class_id' => $class->id,
            'category_id' => $category->id,
            'assessed_by_user_id' => $admin->id,
            'period_label' => 'July 2026',
            'academic_year_id' => $year->id,
            'assessment_date' => '2026-07-10',
            'score' => $score,
            'rating' => $rating,
            'status' => PreschoolMonthlySubmissionStatus::DRAFT,
        ]);
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
