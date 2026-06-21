<?php

namespace Tests\Feature;

use App\Models\PreschoolAcademicTerm;
use App\Models\PreschoolAcademicYear;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolAcademicCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_adminpreschool_can_list_create_and_update_academic_years(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_psac_100', 'preschool.calendar100@hfccf.org');
        Sanctum::actingAs($admin);

        $this->getJson('/api/preschool/settings/academic-years')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['academicYears', 'terms', 'currentContext'],
            ]);

        $created = $this->postJson('/api/preschool/settings/academic-years', [
            'name' => '2026 - 2027',
            'code' => 'AY-2026-2027',
            'description' => 'Primary school year',
            'start_date' => '2026-06-01',
            'end_date' => '2027-05-31',
            'status' => 'active',
            'is_current' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.academicYear.name', '2026 - 2027')
            ->assertJsonPath('data.academicYear.status', 'active');

        $yearId = $created->json('data.academicYear.id');

        $this->putJson('/api/preschool/settings/academic-years/'.$yearId, [
            'name' => '2026 - 2027 Updated',
            'code' => 'AY-2026-2027',
            'description' => 'Updated description',
            'start_date' => '2026-06-01',
            'end_date' => '2027-05-31',
            'status' => 'active',
            'is_current' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.academicYear.name', '2026 - 2027 Updated');
    }

    public function test_superadmin_can_manage_academic_years_and_teacher_is_forbidden(): void
    {
        $superadmin = $this->makeUserWithRole('superadmin', 'usr_psac_101', 'superadmin.calendar101@hfccf.org');
        Sanctum::actingAs($superadmin);

        $created = $this->postJson('/api/preschool/settings/academic-years', [
            'name' => '2025 - 2026',
            'code' => 'AY-2025-2026',
            'start_date' => '2025-06-01',
            'end_date' => '2026-05-31',
            'status' => 'active',
            'is_current' => true,
        ])->assertCreated();

        $yearId = $created->json('data.academicYear.id');

        $this->putJson('/api/preschool/settings/academic-years/'.$yearId, [
            'name' => '2025 - 2026 Revised',
            'start_date' => '2025-06-01',
            'end_date' => '2026-05-31',
            'status' => 'active',
            'is_current' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.academicYear.name', '2025 - 2026 Revised');

        $teacher = $this->makeUserWithRole('teacher-preschool', 'usr_psac_102', 'teacher.calendar102@hfccf.org');
        Sanctum::actingAs($teacher);

        $this->getJson('/api/preschool/settings/academic-years')
            ->assertForbidden();
    }

    public function test_create_academic_year_validation_requires_start_and_end_dates(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_psac_103', 'preschool.calendar103@hfccf.org');
        Sanctum::actingAs($admin);

        $this->postJson('/api/preschool/settings/academic-years', [
            'name' => 'Invalid Year',
            'code' => 'AY-INVALID',
            'start_date' => '2026-06-01',
            'end_date' => '2026-05-31',
            'status' => 'active',
        ])
            ->assertUnprocessable();
    }

    public function test_activate_academic_year_deactivates_previous_active_year(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_psac_104', 'preschool.calendar104@hfccf.org');
        Sanctum::actingAs($admin);

        $first = PreschoolAcademicYear::query()->create([
            'code' => 'AY-2024-2025',
            'label' => '2024 - 2025',
            'start_date' => '2024-06-01',
            'end_date' => '2025-05-31',
            'status' => 'active',
            'is_current' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $second = PreschoolAcademicYear::query()->create([
            'code' => 'AY-2025-2026',
            'label' => '2025 - 2026',
            'start_date' => '2025-06-01',
            'end_date' => '2026-05-31',
            'status' => 'draft',
            'is_current' => false,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->postJson('/api/preschool/settings/academic-years/'.$second->id.'/activate')
            ->assertOk()
            ->assertJsonPath('data.academicYear.isCurrent', true);

        $this->assertDatabaseHas('preschool_academic_years', [
            'id' => $second->id,
            'is_current' => 1,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('preschool_academic_years', [
            'id' => $first->id,
            'is_current' => 0,
        ]);
    }

    public function test_term_must_stay_inside_the_selected_academic_year(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_psac_105', 'preschool.calendar105@hfccf.org');
        Sanctum::actingAs($admin);

        $year = PreschoolAcademicYear::query()->create([
            'code' => 'AY-2025-2026',
            'label' => '2025 - 2026',
            'start_date' => '2025-06-01',
            'end_date' => '2026-05-31',
            'status' => 'active',
            'is_current' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->postJson('/api/preschool/settings/terms', [
            'academic_year_id' => $year->id,
            'name' => 'Term 1',
            'start_date' => '2025-05-01',
            'end_date' => '2025-07-01',
            'status' => 'active',
        ])
            ->assertUnprocessable();
    }

    public function test_activate_term_deactivates_previous_active_term_and_keeps_year_alignment(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_psac_106', 'preschool.calendar106@hfccf.org');
        Sanctum::actingAs($admin);

        $year = PreschoolAcademicYear::query()->create([
            'code' => 'AY-2025-2026',
            'label' => '2025 - 2026',
            'start_date' => '2025-06-01',
            'end_date' => '2026-05-31',
            'status' => 'active',
            'is_current' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $firstTerm = PreschoolAcademicTerm::query()->create([
            'academic_year_id' => $year->id,
            'code' => 'TERM-1',
            'name' => 'Term 1',
            'start_date' => '2025-06-01',
            'end_date' => '2025-08-31',
            'status' => 'active',
            'is_current' => true,
            'sort_order' => 1,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $secondTerm = PreschoolAcademicTerm::query()->create([
            'academic_year_id' => $year->id,
            'code' => 'TERM-2',
            'name' => 'Term 2',
            'start_date' => '2025-09-01',
            'end_date' => '2025-11-30',
            'status' => 'draft',
            'is_current' => false,
            'sort_order' => 2,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->postJson('/api/preschool/settings/terms/'.$secondTerm->id.'/activate')
            ->assertOk()
            ->assertJsonPath('data.term.isCurrent', true);

        $this->assertDatabaseHas('preschool_terms', [
            'id' => $secondTerm->id,
            'is_current' => 1,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('preschool_terms', [
            'id' => $firstTerm->id,
            'is_current' => 0,
        ]);
    }

    public function test_dashboard_summary_returns_active_year_and_term(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_psac_107', 'preschool.calendar107@hfccf.org');
        Sanctum::actingAs($admin);

        $year = PreschoolAcademicYear::query()->create([
            'code' => 'AY-2025-2026',
            'label' => '2025 - 2026',
            'start_date' => '2025-06-01',
            'end_date' => '2026-05-31',
            'status' => 'active',
            'is_current' => true,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        PreschoolAcademicTerm::query()->create([
            'academic_year_id' => $year->id,
            'code' => 'TERM-1',
            'name' => 'Term 1',
            'start_date' => '2025-06-01',
            'end_date' => '2025-08-31',
            'status' => 'active',
            'is_current' => true,
            'sort_order' => 1,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->getJson('/api/preschool/settings/dashboard')
            ->assertOk()
            ->assertJsonPath('data.dashboard.academic.activeAcademicYear', '2025 - 2026')
            ->assertJsonPath('data.dashboard.academic.activeAcademicYearDateRange', '2025-06-01 - 2026-05-31')
            ->assertJsonPath('data.dashboard.academic.activeTerm', 'Term 1')
            ->assertJsonPath('data.dashboard.academic.activeTermDateRange', '2025-06-01 - 2025-08-31');
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
}
