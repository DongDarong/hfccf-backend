<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolSettingsDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_adminpreschool_can_retrieve_dashboard_summary(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_psd_100', 'preschool.settings100@hfccf.org');
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/preschool/settings/dashboard');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.dashboard.attendance.late_threshold_minutes', 15)
            ->assertJsonPath('data.dashboard.attendance.absence_alert_days', 3)
            ->assertJsonPath('data.dashboard.attendance.school_days_per_week', 5)
            ->assertJsonPath('data.dashboard.attendance.calendar_events_count', 0)
            ->assertJsonPath('data.dashboard.attendance.school_week.0', 'monday')
            ->assertJsonPath('data.dashboard.attendance.school_week.4', 'friday')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'dashboard' => [
                        'academic' => ['activeAcademicYear', 'activeTerm', 'academicStatus', 'isConfigured'],
                        'attendance' => ['currentAttendanceRules', 'late_threshold_minutes', 'absence_alert_days', 'school_days_per_week', 'calendar_events_count', 'school_week', 'lastUpdated', 'isConfigured'],
                        'payments' => ['currency', 'invoicePrefix', 'receiptPrefix', 'isConfigured'],
                        'assessments' => ['activeGradingScale', 'assessmentCategories', 'assessmentCategoriesCount', 'isConfigured'],
                        'health' => ['alertSeverityLevels', 'healthCategories', 'isConfigured'],
                        'preferences' => ['organizationName', 'language', 'brandingStatus', 'isConfigured'],
                    ],
                ],
            ]);
    }

    public function test_superadmin_can_retrieve_dashboard_summary(): void
    {
        $superadmin = $this->makeUserWithRole('superadmin', 'usr_psd_101', 'superadmin.settings101@hfccf.org');
        Sanctum::actingAs($superadmin);

        $this->getJson('/api/preschool/settings/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'dashboard' => [
                        'academic' => ['activeAcademicYear', 'activeAcademicYearDateRange', 'activeTerm', 'activeTermDateRange', 'academicStatus', 'isConfigured'],
                        'attendance' => ['currentAttendanceRules', 'late_threshold_minutes', 'absence_alert_days', 'school_days_per_week', 'calendar_events_count', 'school_week', 'lastUpdated', 'isConfigured'],
                    ],
                ],
            ]);
    }

    public function test_teacher_is_blocked_from_dashboard_summary(): void
    {
        $teacher = $this->makeUserWithRole('teacher-preschool', 'usr_psd_102', 'teacher.settings102@hfccf.org');
        Sanctum::actingAs($teacher);

        $this->getJson('/api/preschool/settings/dashboard')
            ->assertForbidden()
            ->assertJsonPath('success', false);
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
