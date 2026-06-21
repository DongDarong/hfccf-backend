<?php

namespace Tests\Feature;

use App\Models\PreschoolAcademicTerm;
use App\Models\PreschoolAcademicYear;
use App\Models\PreschoolAssessmentCategory;
use App\Models\PreschoolAssessmentGradingScale;
use App\Models\PreschoolAssessmentReportPeriod;
use App\Models\PreschoolAssessmentSetting;
use App\Models\PreschoolAssessmentWeight;
use App\Models\Role;
use App\Models\User;
use App\Support\PreschoolAssessmentConfigurationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolAssessmentConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_superadmin_can_get_default_settings(): void
    {
        $superadmin = $this->makeUserWithRole('superadmin', 'usr_pac_100', 'superadmin.assessment100@hfccf.org');
        Sanctum::actingAs($superadmin);

        $response = $this->getJson('/api/preschool/settings/assessments');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.settings.passing_score', 60)
            ->assertJsonPath('data.settings.weighting_enabled', false);
    }

    public function test_adminpreschool_can_update_settings(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_pac_101', 'adminpreschool.assessment101@hfccf.org');
        Sanctum::actingAs($admin);

        $this->putJson('/api/preschool/settings/assessments', [
            'passing_score' => 70,
            'grading_scale_type' => 'letter',
            'weighting_enabled' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.settings.passing_score', 70)
            ->assertJsonPath('data.settings.weighting_enabled', true);

        $this->assertDatabaseHas('preschool_assessment_settings', [
            'passing_score' => 70,
            'grading_scale_type' => 'letter',
            'weighting_enabled' => 1,
        ]);
    }

    public function test_teacher_preschool_forbidden(): void
    {
        $teacher = $this->makeUserWithRole('teacher-preschool', 'usr_pac_102', 'teacherpreschool.assessment102@hfccf.org');
        Sanctum::actingAs($teacher);

        $this->getJson('/api/preschool/settings/assessments')->assertForbidden();
    }

    public function test_unrelated_admin_forbidden(): void
    {
        $admin = $this->makeUserWithRole('adminenglish', 'usr_pac_103', 'adminenglish.assessment103@hfccf.org');
        Sanctum::actingAs($admin);

        $this->getJson('/api/preschool/settings/assessments')->assertForbidden();
    }

    public function test_adminscholarship_forbidden(): void
    {
        $admin = $this->makeUserWithRole('adminscholarship', 'usr_pac_104', 'adminscholarship.assessment104@hfccf.org');
        Sanctum::actingAs($admin);

        $this->getJson('/api/preschool/settings/assessments')->assertForbidden();
    }

    public function test_grading_scale_crud_works(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_pac_105', 'adminpreschool.assessment105@hfccf.org');
        Sanctum::actingAs($admin);

        $bands = $this->getJson('/api/preschool/settings/assessments/grading-scale')
            ->assertOk()
            ->json('data.items');

        $bandId = data_get(collect($bands)->firstWhere('grade', 'A'), 'id');
        $this->assertNotNull($bandId);

        $this->putJson('/api/preschool/settings/assessments/grading-scale/'.$bandId, [
            'minimum_score' => 90,
            'maximum_score' => 94,
        ])
            ->assertOk()
            ->assertJsonPath('data.band.maximum_score', 94);

        $create = $this->postJson('/api/preschool/settings/assessments/grading-scale', [
            'name' => 'Superior',
            'grade' => 'S',
            'minimum_score' => 95,
            'maximum_score' => 100,
            'color' => '#111111',
            'sort_order' => 1,
            'is_passing' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.band.grade', 'S');

        $bandId = $create->json('data.band.id');

        $this->deleteJson('/api/preschool/settings/assessments/grading-scale/'.$bandId)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('preschool_assessment_grading_scales', [
            'id' => $bandId,
        ]);
    }

    public function test_category_crud_and_archive_work(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_pac_106', 'adminpreschool.assessment106@hfccf.org');
        Sanctum::actingAs($admin);

        $category = $this->postJson('/api/preschool/settings/assessments/categories', [
            'code' => 'quiz',
            'name' => 'Quiz',
            'description' => 'Short test',
            'sort_order' => 1,
            'is_active' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.category.name', 'Quiz');

        $categoryId = $category->json('data.category.id');

        $this->putJson('/api/preschool/settings/assessments/categories/'.$categoryId, [
            'name' => 'Quiz Updated',
            'is_active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.category.name', 'Quiz Updated')
            ->assertJsonPath('data.category.isActive', false);

        $this->postJson('/api/preschool/settings/assessments/categories/'.$categoryId.'/archive')
            ->assertOk()
            ->assertJsonPath('data.category.status', 'archived');

        $this->assertSoftDeleted('preschool_assessment_categories', [
            'id' => $categoryId,
        ]);
    }

    public function test_report_period_crud_and_archive_work(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_pac_107', 'adminpreschool.assessment107@hfccf.org');
        Sanctum::actingAs($admin);
        $academicYear = $this->createAcademicYear('AY-PAC-01', '2026 - 2027');
        $term = $this->createTerm($academicYear->id, 'TERM-1', 'Term 1');

        $period = $this->postJson('/api/preschool/settings/assessments/report-periods', [
            'academic_year_id' => $academicYear->id,
            'term_id' => $term->id,
            'name' => 'Midterm',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'is_active' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.period.name', 'Midterm');

        $periodId = $period->json('data.period.id');

        $this->putJson('/api/preschool/settings/assessments/report-periods/'.$periodId, [
            'name' => 'Midterm Updated',
        ])
            ->assertOk()
            ->assertJsonPath('data.period.name', 'Midterm Updated');

        $this->postJson('/api/preschool/settings/assessments/report-periods/'.$periodId.'/archive')
            ->assertOk()
            ->assertJsonPath('data.period.is_active', false);

        $this->assertSoftDeleted('preschool_assessment_report_periods', [
            'id' => $periodId,
        ]);
    }

    public function test_weight_validation_requires_total_one_hundred(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_pac_108', 'adminpreschool.assessment108@hfccf.org');
        Sanctum::actingAs($admin);

        $categoryA = $this->createCategory('quiz-a', 'Quiz A');
        $categoryB = $this->createCategory('quiz-b', 'Quiz B');

        $this->putJson('/api/preschool/settings/assessments/weights', [
            'weights' => [
                ['category_id' => $categoryA->id, 'percentage' => 40],
                ['category_id' => $categoryB->id, 'percentage' => 40],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonFragment(['Assessment weights must total 100%.']);
    }

    public function test_grade_calculation_uses_configured_scale(): void
    {
        $service = app(PreschoolAssessmentConfigurationService::class);
        $this->assertSame('A', $service->getGradeForScore(95));
        $this->assertSame('B', $service->getGradeForScore(82));
        $this->assertSame('C', $service->getGradeForScore(71));
        $this->assertSame('D', $service->getGradeForScore(61));
        $this->assertSame('F', $service->getGradeForScore(20));
    }

    public function test_dashboard_attendance_summary_returns_live_values(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_pac_109', 'adminpreschool.assessment109@hfccf.org');
        Sanctum::actingAs($admin);

        $assessmentService = app(PreschoolAssessmentConfigurationService::class);
        $assessmentService->updateSettings([
            'passing_score' => 68,
            'grading_scale_type' => 'letter',
            'weighting_enabled' => true,
        ], $admin);
        $this->createCategory('quiz-c', 'Quiz C');
        $academicYear = $this->createAcademicYear('AY-PAC-02', '2027 - 2028');
        $this->createReportPeriod($academicYear->id, 'Report Period 1');
        $this->createReportPeriod($academicYear->id, 'Report Period 2');

        $this->getJson('/api/preschool/settings/dashboard')
            ->assertOk()
            ->assertJsonPath('data.dashboard.assessments.passing_score', 68)
            ->assertJsonPath('data.dashboard.assessments.weighting_enabled', true)
            ->assertJsonPath('data.dashboard.assessments.assessment_categories_count', PreschoolAssessmentCategory::query()->where('is_active', true)->count())
            ->assertJsonPath('data.dashboard.assessments.report_periods_count', PreschoolAssessmentReportPeriod::query()->where('is_active', true)->count())
            ->assertJsonPath('data.dashboard.assessments.grade_bands_count', PreschoolAssessmentGradingScale::query()->count());
    }

    private function makeUserWithRole(string $roleCode, string $id, string $email): User
    {
        $role = Role::query()->with('permissions')->findOrFail($roleCode);

        $user = User::query()->create([
            'id' => $id,
            'first_name' => ucfirst(str_replace('-', ' ', $roleCode)),
            'last_name' => 'User',
            'username' => ucfirst(str_replace('-', ' ', $roleCode)).' User',
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

    private function createAcademicYear(string $code, string $label): PreschoolAcademicYear
    {
        return PreschoolAcademicYear::query()->create([
            'code' => $code,
            'label' => $label,
            'description' => 'Test academic year',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
            'is_current' => true,
            'notes' => null,
        ]);
    }

    private function createTerm(int $academicYearId, string $code, string $name): PreschoolAcademicTerm
    {
        return PreschoolAcademicTerm::query()->create([
            'academic_year_id' => $academicYearId,
            'code' => $code,
            'name' => $name,
            'description' => null,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
            'is_current' => true,
            'sort_order' => 1,
            'notes' => null,
        ]);
    }

    private function createCategory(string $code, string $name): PreschoolAssessmentCategory
    {
        $admin = User::query()->where('role_code', 'superadmin')->firstOrFail();

        return PreschoolAssessmentCategory::query()->create([
            'code' => $code,
            'name' => $name,
            'description' => null,
            'sort_order' => 1,
            'is_active' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
    }

    private function createReportPeriod(int $academicYearId, string $name): PreschoolAssessmentReportPeriod
    {
        return PreschoolAssessmentReportPeriod::query()->create([
            'academic_year_id' => $academicYearId,
            'term_id' => null,
            'name' => $name,
            'start_date' => '2027-01-01',
            'end_date' => '2027-03-31',
            'is_active' => true,
        ]);
    }
}
