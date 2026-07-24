<?php

namespace Tests\Feature;

use App\Models\PreschoolAcademicYear;
use App\Models\PreschoolAttendanceRecord;
use App\Models\PreschoolClass;
use App\Models\PreschoolStudent;
use App\Models\User;
use App\Services\PreschoolMonthlyAttendancePdfService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class PreschoolMonthlyAttendancePdfDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_monthly_attendance_pdf_download_returns_backend_generated_pdf_attachment(): void
    {
        $context = $this->createReportContext();
        $this->fakePdfBrowserProcess();

        $response = $this->actingWithToken($context['admin'])->get('/api/preschool/reports/attendance/monthly/download?'.http_build_query([
            'academic_year_id' => $context['year']->id,
            'class_id' => $context['class']->id,
            'month' => 7,
            'year' => 2026,
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-31',
        ]));

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename="AttendanceReport_Monthly_2026-07_'.now()->toDateString().'.pdf"');

        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
        Process::assertRan(fn ($process, $result): bool => collect($process->command)->contains(fn ($argument) => str_starts_with($argument, '--print-to-pdf=')));
        $this->assertSame([], File::files(storage_path('app/tmp/preschool-monthly-attendance')));
    }

    public function test_monthly_attendance_html_uses_selected_academic_year_and_month_filters(): void
    {
        $context = $this->createReportContext();

        $html = app(PreschoolMonthlyAttendancePdfService::class)->renderHtml([
            'academic_year_id' => $context['year']->id,
            'class_id' => $context['class']->id,
            'month' => 7,
            'year' => 2026,
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-31',
        ]);

        $this->assertStringContainsString('size: A4 landscape', $html);
        $this->assertStringContainsString('margin: 8mm 8mm 10mm', $html);
        $this->assertStringContainsString('បញ្ជីវត្តមានសិស្សប្រចាំខែ', $html);
        $this->assertStringContainsString('បញ្ជីវត្តមានសិស្សថ្នាក់ Lotus ប្រចាំខែ កក្កដា', $html);
        $this->assertStringContainsString('ឆ្នាំសិក្សា Academic Year 2026', $html);
        $this->assertStringContainsString('គោត្តនាម-នាម', $html);
        $this->assertStringContainsString('ថ្ងៃខែឆ្នាំកំណើត', $html);
        $this->assertStringContainsString('សុភា រ៉ា', $html);
        $this->assertStringContainsString('សុខា Chan', $html);
        $this->assertStringContainsString('ចំនួនសិស្សសរុប៖ 2 នាក់', $html);
        $this->assertStringContainsString('ចំនួនសិស្សស្រី៖ 2 នាក់', $html);
        $this->assertStringContainsString('P = វត្តមាន', $html);
        $this->assertStringContainsString('A = អវត្តមាន', $html);
        $this->assertStringContainsString('L = មកយឺត', $html);
        $this->assertStringContainsString('E = មានច្បាប់', $html);
        $this->assertStringContainsString('<td class="col-no">20</td>', $html);
        $this->assertStringNotContainsString('អត្តលេខ', $html);
        $this->assertStringNotContainsString('ភាគរយវត្តមាន', $html);
        $this->assertStringNotContainsString('ចន្លោះកាលបរិច្ឆេទ', $html);
        $this->assertStringNotContainsString('គ្រូប្រចាំថ្នាក់', $html);
        $this->assertStringNotContainsString('PS-OTHER-YEAR', $html);
        $this->assertStringNotContainsString('PS-ATT-0001', $html);
        $this->assertStringNotContainsString('PS-INACTIVE', $html);
        $this->assertStringNotContainsString('August-only note', $html);
        $this->assertStringNotContainsString('Dashboard', $html);
        $this->assertStringNotContainsString('Attendance Summary', $html);
    }

    public function test_monthly_attendance_report_endpoint_uses_canonical_dataset_shared_with_pdf(): void
    {
        $context = $this->createReportContext();

        $query = [
            'academic_year_id' => $context['year']->id,
            'class_id' => $context['class']->id,
            'month' => 7,
            'year' => 2026,
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-31',
        ];

        $response = $this->actingWithToken($context['admin'])
            ->getJson('/api/preschool/reports/attendance/monthly?'.http_build_query($query));

        $response->assertOk()
            ->assertJsonPath('data.summary.totalStudents', 2)
            ->assertJsonPath('data.summary.femaleStudents', 2)
            ->assertJsonPath('data.summary.totalRecords', 3)
            ->assertJsonPath('data.summary.present', 1)
            ->assertJsonPath('data.summary.absent', 1)
            ->assertJsonPath('data.summary.late', 1)
            ->assertJsonPath('data.summary.excused', 0)
            ->assertJsonPath('data.compatibility.includes_null_academic_year_attendance_records', true);

        $html = app(PreschoolMonthlyAttendancePdfService::class)->renderHtml($query);

        $this->assertStringContainsString('ចំនួនសិស្សសរុប៖ 2 នាក់', $html);
        $this->assertStringContainsString('វត្តមានសរុប៖ 1', $html);
        $this->assertStringContainsString('អវត្តមានសរុប៖ 1', $html);
        $this->assertStringContainsString('មកយឺតសរុប៖ 1', $html);
        $this->assertStringNotContainsString('August-only note', $html);
        $this->assertStringNotContainsString('PS-OTHER-YEAR', $html);
    }

    public function test_monthly_attendance_report_roster_accepts_same_calendar_year_academic_year_rows(): void
    {
        $admin = User::factory()->asAdminPreschool()->create();
        $selectedYear = PreschoolAcademicYear::factory()->create([
            'label' => 'ឆ្នាំសិក្សា២០២៦',
            'code' => '001',
        ]);
        $enrollmentYear = PreschoolAcademicYear::factory()->create([
            'label' => 'Academic Year QA 2026',
            'code' => 'AY-QA-2026',
        ]);
        $class = PreschoolClass::factory()->create([
            'name' => 'Room113',
        ]);
        $student = $this->createStudent('PS-ROOM113-001', 'ចាន់', 'ហេង');

        $this->attachStudent($class->id, $student->id, $enrollmentYear->id, 'active', 'active', $enrollmentYear->label);
        $this->createAttendance($admin, $class, $student, $selectedYear, '2026-07-08', 'present');

        $query = [
            'academic_year_id' => $selectedYear->id,
            'class_id' => $class->id,
            'month' => 7,
            'year' => 2026,
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-31',
        ];

        $response = $this->actingWithToken($admin)
            ->getJson('/api/preschool/reports/attendance/monthly?'.http_build_query($query));

        $response->assertOk()
            ->assertJsonPath('data.summary.totalStudents', 1)
            ->assertJsonPath('data.summary.totalRecords', 1)
            ->assertJsonPath('data.summary.present', 1)
            ->assertJsonPath('data.students.0.studentCode', 'PS-ROOM113-001')
            ->assertJsonPath('data.compatibility.includes_same_calendar_year_academic_year_ids', true);

        $html = app(PreschoolMonthlyAttendancePdfService::class)->renderHtml($query);

        $this->assertStringContainsString('ចាន់ ហេង', $html);
        $this->assertStringContainsString('ចំនួនសិស្សសរុប៖ 1 នាក់', $html);
        $this->assertStringContainsString('វត្តមានសរុប៖ 1', $html);
    }

    public function test_monthly_attendance_pdf_validates_required_filters(): void
    {
        $context = $this->createReportContext();

        $this->actingWithToken($context['admin'])->getJson('/api/preschool/reports/attendance/monthly/download?'.http_build_query([
            'class_id' => $context['class']->id,
            'month' => 7,
            'year' => 2026,
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-31',
        ]))->assertUnprocessable();
    }

    public function test_teacher_cannot_download_monthly_attendance_pdf(): void
    {
        $context = $this->createReportContext();
        $teacher = User::factory()->asTeacherPreschool()->create();

        $this->actingWithToken($teacher)->getJson('/api/preschool/reports/attendance/monthly/download?'.http_build_query([
            'academic_year_id' => $context['year']->id,
            'class_id' => $context['class']->id,
            'month' => 7,
            'year' => 2026,
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-31',
        ]))->assertForbidden();
    }

    public function test_monthly_attendance_pdf_download_cleans_up_temp_files_when_browser_process_fails(): void
    {
        $context = $this->createReportContext();

        config()->set('services.preschool_pdf.browser_binary', PHP_BINARY);
        File::ensureDirectoryExists(storage_path('app/tmp/preschool-monthly-attendance'));
        foreach (File::files(storage_path('app/tmp/preschool-monthly-attendance')) as $file) {
            File::delete($file->getPathname());
        }

        Process::fake([
            '*' => Process::result('', 'simulated renderer failure', 1),
        ])->preventStrayProcesses();

        $response = $this->actingWithToken($context['admin'])->getJson('/api/preschool/reports/attendance/monthly/download?'.http_build_query([
            'academic_year_id' => $context['year']->id,
            'class_id' => $context['class']->id,
            'month' => 7,
            'year' => 2026,
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-31',
        ]));

        $response->assertStatus(500)
            ->assertJsonPath('message', 'Attendance PDF rendering is temporarily unavailable.');

        $this->assertSame([], File::files(storage_path('app/tmp/preschool-monthly-attendance')));
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

        $studentOne = $this->createStudent('PS-ATT-0001', 'សុភា', 'រ៉ា');
        $studentTwo = $this->createStudent('PS-ATT-0002', 'សុខា', 'Chan');
        $otherYearStudent = $this->createStudent('PS-OTHER-YEAR', 'Other', 'Year');
        $inactiveStudent = $this->createStudent('PS-INACTIVE', 'Inactive', 'Student');

        $this->attachStudent($class->id, $studentOne->id, null, 'active', 'active', $year->label);
        $this->attachStudent($class->id, $studentTwo->id, null, 'active', 'active');
        $this->attachStudent($class->id, $otherYearStudent->id, $otherYear->id, 'active', 'active');
        $this->attachStudent($class->id, $inactiveStudent->id, $year->id, 'inactive', 'inactive');

        $this->createAttendance($admin, $class, $studentOne, null, '2026-07-01', 'present');
        $this->createAttendance($admin, $class, $studentOne, null, '2026-07-02', 'absent');
        $this->createAttendance($admin, $class, $studentTwo, null, '2026-07-01', 'late');
        $this->createAttendance($admin, $class, $studentTwo, null, '2026-08-01', 'present', 'August-only note');
        $this->createAttendance($admin, $class, $otherYearStudent, $otherYear, '2026-07-01', 'present');

        return compact('admin', 'year', 'class');
    }

    private function createStudent(string $code, string $firstName, string $lastName): PreschoolStudent
    {
        return PreschoolStudent::factory()->create([
            'student_code' => $code,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'gender' => 'female',
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

    private function createAttendance(
        User $admin,
        PreschoolClass $class,
        PreschoolStudent $student,
        ?PreschoolAcademicYear $year,
        string $date,
        string $status,
        ?string $note = null,
    ): void {
        PreschoolAttendanceRecord::query()->create([
            'class_id' => $class->id,
            'student_id' => $student->id,
            'recorded_by_user_id' => $admin->id,
            'attendance_date' => $date,
            'status' => $status,
            'note' => $note,
            'academic_year_id' => $year?->id,
            'term_id' => null,
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
