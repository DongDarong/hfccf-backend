<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\PreschoolAssessmentStatus;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolReportApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_view_finalized_preschool_reports(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psr-100', 'preschool.report100@hfccf.org');
        Sanctum::actingAs($admin);

        $class = $this->createPreschoolClass('PS-REPORT-100', 'Report Class');
        $studentOne = $this->createPreschoolStudent('PS-REPORT-101', 'Lina', 'Chan');
        $studentTwo = $this->createPreschoolStudent('PS-REPORT-102', 'Dara', 'Sok');
        $this->attachStudentToClass($class->id, $studentOne->id);
        $this->attachStudentToClass($class->id, $studentTwo->id);

        $learningProgressId = $this->categoryId('learning_progress');
        $behaviorId = $this->categoryId('behavior');

        $this->insertAssessment($studentOne->id, $class->id, $learningProgressId, 'Term 1', '2026-05-10', 85, PreschoolAssessmentStatus::FINALIZED, 'Strong focus.', $admin->id, $admin->id);
        $this->insertAssessment($studentOne->id, $class->id, $behaviorId, 'Term 1', '2026-05-12', 90, PreschoolAssessmentStatus::FINALIZED, 'Kind to classmates.', $admin->id, $admin->id);
        $this->insertAssessment($studentOne->id, $class->id, $behaviorId, 'Term 1', '2026-05-13', 0, PreschoolAssessmentStatus::DRAFT, 'Draft note.', $admin->id, null);
        $this->insertAssessment($studentOne->id, $class->id, $behaviorId, 'Term 1', '2026-05-14', 0, PreschoolAssessmentStatus::ARCHIVED, 'Archived note.', $admin->id, null);
        $this->insertAssessment($studentTwo->id, $class->id, $learningProgressId, 'Term 1', '2026-05-11', 92, PreschoolAssessmentStatus::FINALIZED, 'Excellent progress.', $admin->id, $admin->id);

        $this->insertAttendance($class->id, $studentOne->id, '2026-05-10', 'present');
        $this->insertAttendance($class->id, $studentOne->id, '2026-05-11', 'late');
        $this->insertAttendance($class->id, $studentTwo->id, '2026-05-11', 'present');

        $this->getJson('/api/preschool/report-periods')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.periods.0.label', 'Term 1')
            ->assertJsonPath('data.periods.0.assessmentCount', 3);

        $this->getJson("/api/preschool/students/{$studentOne->id}/reports")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.periods.0.label', 'Term 1')
            ->assertJsonPath('data.report.summary.finalizedAssessments', 2)
            ->assertJsonPath('data.report.attendanceSummary.presentCount', 1)
            ->assertJsonPath('data.report.assessments.0.status', PreschoolAssessmentStatus::FINALIZED);

        $this->getJson('/api/preschool/students/'.$studentOne->id.'/reports/'.rawurlencode('Term 1'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.report.summary.finalizedAssessments', 2)
            ->assertJsonPath('data.report.categorySummaries.0.category.code', 'behavior');

        $this->getJson("/api/preschool/classes/{$class->id}/reports")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.report.summary.finalizedAssessments', 3)
            ->assertJsonPath('data.report.summary.studentCount', 2)
            ->assertJsonPath('data.report.studentSummaries.0.student.fullName', 'Dara Sok');

        $this->getJson('/api/preschool/classes/'.$class->id.'/reports/'.rawurlencode('Term 1'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.report.summary.finalizedAssessments', 3)
            ->assertJsonPath('data.report.assessments.0.status', PreschoolAssessmentStatus::FINALIZED);
    }

    public function test_teacher_access_is_limited_to_assigned_students_and_classes(): void
    {
        $teacher = $this->makeUserWithRole('teacher-preschool', 'psr-110', 'preschool.report110@hfccf.org');
        Sanctum::actingAs($teacher);

        $ownClass = $this->createPreschoolClass('PS-REPORT-110', 'Teacher Report Class', $teacher->id, trim($teacher->first_name.' '.$teacher->last_name));
        $ownStudent = $this->createPreschoolStudent('PS-REPORT-111', 'Mina', 'Khan');
        $this->attachStudentToClass($ownClass->id, $ownStudent->id);
        $this->insertAssessment($ownStudent->id, $ownClass->id, $this->categoryId('behavior'), 'Term 2', '2026-05-15', 88, PreschoolAssessmentStatus::FINALIZED, 'Great transitions.', $teacher->id, $teacher->id);

        $otherTeacher = $this->makeUserWithRole('teacher-preschool', 'psr-112', 'preschool.report112@hfccf.org');
        $otherClass = $this->createPreschoolClass('PS-REPORT-112', 'Other Teacher Report Class', $otherTeacher->id, trim($otherTeacher->first_name.' '.$otherTeacher->last_name));
        $otherStudent = $this->createPreschoolStudent('PS-REPORT-113', 'Other', 'Student');
        $this->attachStudentToClass($otherClass->id, $otherStudent->id);
        $this->insertAssessment($otherStudent->id, $otherClass->id, $this->categoryId('learning_progress'), 'Term 2', '2026-05-15', 91, PreschoolAssessmentStatus::FINALIZED, 'Not visible.', $otherTeacher->id, $otherTeacher->id);

        $this->getJson('/api/preschool/report-periods')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['label' => 'Term 2']);

        $this->getJson("/api/preschool/students/{$ownStudent->id}/reports")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson("/api/preschool/students/{$otherStudent->id}/reports")
            ->assertForbidden();

        $this->getJson('/api/preschool/classes/'.$otherClass->id.'/reports/'.rawurlencode('Term 2'))
            ->assertForbidden();
    }

    public function test_unauthenticated_users_are_blocked_from_preschool_reports(): void
    {
        $this->getJson('/api/preschool/report-periods')
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_centralized_report_dashboard_and_definitions_are_available_to_admins(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psr-120', 'preschool.report120@hfccf.org');
        Sanctum::actingAs($admin);

        [$academicYearId, $termId] = $this->createAcademicYearAndTerm('AY-REPORT-120', 'TERM-REPORT-120');

        $this->getJson('/api/preschool/reports/definitions')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['key' => 'enrollment']);

        $this->getJson('/api/preschool/reports/dashboard?academicYearId='.$academicYearId.'&termId='.$termId)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.dashboard.report', 'dashboard')
            ->assertJsonPath('data.dashboard.section', 'operations')
            ->assertJsonStructure([
                'data' => [
                    'dashboard' => [
                        'summary',
                        'cards',
                        'trend',
                        'modules',
                        'risk',
                        'filters',
                    ],
                ],
            ]);

        $this->getJson('/api/preschool/reports/enrollments?academicYearId='.$academicYearId.'&termId='.$termId)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.report', 'enrollments');
    }

    public function test_teachers_can_read_limited_reports_but_cannot_export_or_read_payment_reports(): void
    {
        $teacher = $this->makeUserWithRole('teacher-preschool', 'psr-121', 'preschool.report121@hfccf.org');
        Sanctum::actingAs($teacher);

        $this->getJson('/api/preschool/reports/attendance')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson('/api/preschool/reports/payments')
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->getJson('/api/preschool/reports/export?section=attendance&format=csv')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_admin_export_creates_governance_and_audit_records(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psr-122', 'preschool.report122@hfccf.org');
        Sanctum::actingAs($admin);

        [$academicYearId, $termId] = $this->createAcademicYearAndTerm('AY-REPORT-122', 'TERM-REPORT-122');

        $response = $this->getJson('/api/preschool/reports/export?section=attendance&format=excel&academicYearId='.$academicYearId.'&termId='.$termId)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.export.section', 'attendance')
            ->assertJsonPath('data.export.format', 'excel')
            ->assertJsonPath('data.export.encoding', 'base64');

        $exportRecordId = $response->json('data.exportRecordId');

        $this->assertDatabaseHas('preschool_report_export_records', [
            'id' => $exportRecordId,
            'actor_user_id' => $admin->id,
            'export_type' => 'attendance_report',
            'export_format' => 'excel',
            'term_id' => $termId,
        ]);

        $this->assertDatabaseHas('preschool_lifecycle_audit_logs', [
            'actor_user_id' => $admin->id,
            'action_type' => 'report_export.created',
            'entity_type' => 'preschool_report_export',
            'entity_id' => (string) $exportRecordId,
        ]);
    }

    private function makeUserWithRole(string $roleCode, string $id, string $email): User
    {
        $role = Role::query()->with('permissions')->findOrFail($roleCode);

        $user = User::query()->create([
            'id' => $id,
            'first_name' => ucfirst(str_replace('-', ' ', $roleCode)),
            'last_name' => 'User',
            'username' => $roleCode.'-'.$id,
            'email' => $email,
            'phone' => '+855 12 555 555',
            'role_code' => $role->code,
            'department_code' => $role->department_code,
            'status' => 'active',
            'password' => 'secret-pass',
        ]);

        $rows = $role->permissions->map(static fn ($permission) => [
            'user_id' => $user->id,
            'permission_code' => $permission->code,
        ])->all();

        if ($rows !== []) {
            DB::table('user_permissions')->insert($rows);
        }

        return $user;
    }

    private function createPreschoolClass(string $code, string $name, ?string $teacherId = null, ?string $teacherDisplayName = null): object
    {
        $classId = DB::table('preschool_classes')->insertGetId([
            'code' => $code,
            'name' => $name,
            'teacher_user_id' => $teacherId,
            'teacher_display_name' => $teacherDisplayName,
            'level' => 'Nursery',
            'schedule' => 'Mon-Fri 8:00 AM',
            'students_count' => 0,
            'status' => 'active',
            'room' => 'Room A1',
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('preschool_classes')->where('id', $classId)->first();
    }

    private function createPreschoolStudent(string $code, string $firstName, string $lastName): object
    {
        $studentId = DB::table('preschool_students')->insertGetId([
            'student_code' => $code,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'gender' => 'female',
            'date_of_birth' => '2020-01-01',
            'guardian_name' => 'Guardian',
            'guardian_phone' => '+855 12 000 000',
            'address' => 'Phnom Penh',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('preschool_students')->where('id', $studentId)->first();
    }

    private function attachStudentToClass(int $classId, int $studentId): void
    {
        DB::table('preschool_class_students')->insert([
            'class_id' => $classId,
            'student_id' => $studentId,
            'enrolled_at' => now(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertAssessment(
        int $studentId,
        int $classId,
        int $categoryId,
        string $periodLabel,
        string $assessmentDate,
        int|float $score,
        string $status,
        string $observation,
        ?string $assessedByUserId = null,
        ?string $finalizedByUserId = null,
    ): void {
        DB::table('preschool_student_assessments')->insert([
            'student_id' => $studentId,
            'class_id' => $classId,
            'category_id' => $categoryId,
            'assessed_by_user_id' => $assessedByUserId ?? 'psr-100',
            'period_label' => $periodLabel,
            'assessment_date' => $assessmentDate,
            'score' => $score,
            'rating' => $score >= 90 ? 'excellent' : 'proficient',
            'observation' => $observation,
            'teacher_comment' => $observation,
            'status' => $status,
            'finalized_at' => $status === PreschoolAssessmentStatus::FINALIZED ? now() : null,
            'finalized_by_user_id' => $status === PreschoolAssessmentStatus::FINALIZED ? ($finalizedByUserId ?? 'psr-100') : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertAttendance(int $classId, int $studentId, string $date, string $status): void
    {
        DB::table('preschool_attendance_records')->insert([
            'class_id' => $classId,
            'student_id' => $studentId,
            'recorded_by_user_id' => 'psr-100',
            'attendance_date' => $date,
            'status' => $status,
            'note' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function categoryId(string $code): int
    {
        return (int) DB::table('preschool_assessment_categories')
            ->where('code', $code)
            ->value('id');
    }

    /**
     * @return array{0:int,1:int}
     */
    private function createAcademicYearAndTerm(string $yearCode, string $termCode): array
    {
        $academicYearId = DB::table('preschool_academic_years')->insertGetId([
            'code' => $yearCode,
            'label' => '2026 - 2027',
            'description' => null,
            'start_date' => '2026-07-01',
            'end_date' => '2027-04-30',
            'status' => 'active',
            'is_current' => true,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $termId = DB::table('preschool_terms')->insertGetId([
            'academic_year_id' => $academicYearId,
            'code' => $termCode,
            'name' => 'Term 1',
            'description' => null,
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
            'is_current' => true,
            'sort_order' => 1,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$academicYearId, $termId];
    }
}
