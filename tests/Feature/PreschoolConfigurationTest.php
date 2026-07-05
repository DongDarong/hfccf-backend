<?php

namespace Tests\Feature;

use App\Models\PreschoolAcademicYear;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_teacher_can_read_the_shared_settings_backbone(): void
    {
        $teacher = $this->makeUserWithRole('teacher-preschool', 'usr_pcfg_100', 'teacher.pcfg100@hfccf.org');
        Sanctum::actingAs($teacher);

        $this->getJson('/api/preschool/settings/backbone')
            ->assertOk()
            ->assertJsonPath('data.settings.groups.academic.key', 'academic')
            ->assertJsonPath('data.settings.groups.attendance.key', 'attendance')
            ->assertJsonPath('data.settings.metadata.source', 'defaults');
    }

    public function test_admin_can_update_backbone_and_create_audit_log(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_pcfg_101', 'admin.pcfg101@hfccf.org');
        Sanctum::actingAs($admin);

        $response = $this->patchJson('/api/preschool/settings/backbone', [
            'academicYear' => [
                'currentAcademicYear' => '2026 - 2027',
                'startDate' => '2026-07-01',
                'endDate' => '2027-04-30',
                'status' => 'active',
            ],
            'attendance' => [
                'markingWindow' => '07:45 - 08:15',
                'lateThreshold' => 20,
                'absenceRule' => 'window-and-threshold',
                'teacherCanEditAttendance' => true,
            ],
            'assessment' => [
                'assessmentCycle' => 'term',
                'finalizationMode' => 'publish-only',
                'defaultTemplate' => 'PRESCHOOL-DEVELOPMENT-CORE',
                'requireTeacherNotes' => true,
            ],
            'schedule' => [
                'weeklyMode' => 'five-day',
                'defaultSlotMinutes' => 45,
                'planningWindow' => 'weekly',
                'allowTeacherOverrides' => false,
            ],
            'enrollment' => [
                'enrollmentCycle' => 'term',
                'defaultClassLevel' => 'nursery',
                'transferPolicy' => 'admin-only',
                'capacityReviewMode' => 'manual',
            ],
            'payment' => [
                'defaultTuitionFee' => 125,
                'paymentCycle' => 'monthly',
                'dueDay' => 5,
                'lateFeeRule' => 'fixed',
                'enableOverdueReminders' => true,
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.settings.academicYear.currentAcademicYear', '2026 - 2027')
            ->assertJsonPath('data.settings.groups.academic.settings.academicYear.currentAcademicYear', '2026 - 2027')
            ->assertJsonPath('data.settings.metadata.lastUpdatedBy', $admin->id);

        $this->assertDatabaseHas('preschool_settings_backbone', [
            'key' => 'academic-backbone',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'domain' => 'preschool',
            'action' => 'settings.updated',
            'entity_type' => 'preschool_settings_backbone',
            'entity_id' => 'academic-backbone',
        ]);
    }

    public function test_unrelated_admin_cannot_read_or_write_backbone(): void
    {
        $admin = $this->makeUserWithRole('adminenglish', 'usr_pcfg_102', 'admin.english.pcfg102@hfccf.org');
        Sanctum::actingAs($admin);

        $this->getJson('/api/preschool/settings/backbone')->assertForbidden();
        $this->patchJson('/api/preschool/settings/backbone', [])->assertForbidden();
    }

    public function test_report_period_requires_a_term(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_pcfg_103', 'admin.pcfg103@hfccf.org');
        Sanctum::actingAs($admin);

        $year = PreschoolAcademicYear::query()->create([
            'code' => 'AY-PCFG-01',
            'label' => '2026 - 2027',
            'description' => null,
            'start_date' => '2026-07-01',
            'end_date' => '2027-04-30',
            'status' => 'active',
            'is_current' => true,
            'notes' => null,
        ]);

        $this->postJson('/api/preschool/settings/assessments/report-periods', [
            'academic_year_id' => $year->id,
            'term_id' => null,
            'name' => 'Midterm',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'is_active' => true,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('data.errors.term_id.0', 'The term id field is required.');
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

