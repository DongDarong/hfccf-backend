<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_view_reports_dashboard(): void
    {
        Sanctum::actingAs($this->makeUser('superadmin'));

        $response = $this->getJson('/api/preschool/reports/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.dashboard.report', 'dashboard')
            ->assertJsonPath('data.dashboard.kpis.attendance_rate', 0)
            ->assertJsonPath('data.dashboard.kpis.revenue', 0)
            ->assertJsonPath('data.dashboard.modules.attendance.attendance_rate', 0)
            ->assertJsonPath('data.filters.export_formats.0.value', 'pdf');
    }

    public function test_teacher_preschool_can_view_selected_reports_only(): void
    {
        Sanctum::actingAs($this->makeUser('teacher-preschool'));

        $this->getJson('/api/preschool/reports/attendance')->assertOk();
        $this->getJson('/api/preschool/reports/assessments')->assertOk();
        $this->getJson('/api/preschool/reports/health')->assertForbidden();
        $this->getJson('/api/preschool/reports/payments')->assertForbidden();
        $this->getJson('/api/preschool/reports/guardians')->assertForbidden();
    }

    public function test_unrelated_admin_is_forbidden_from_reports(): void
    {
        Sanctum::actingAs($this->makeUser('adminenglish'));

        $this->getJson('/api/preschool/reports/dashboard')->assertForbidden();
        $this->getJson('/api/preschool/reports/attendance')->assertForbidden();
    }

    public function test_export_endpoint_returns_prepared_export_payload(): void
    {
        Sanctum::actingAs($this->makeUser('adminpreschool'));

        $response = $this->getJson('/api/preschool/reports/export?section=attendance&format=csv');

        $response->assertOk()
            ->assertJsonPath('data.export.section', 'attendance')
            ->assertJsonPath('data.export.format', 'csv')
            ->assertJsonPath('data.export.mime_type', 'text/csv;charset=utf-8');
    }

    public function test_service_risk_summary_aggregates_module_risks(): void
    {
        Sanctum::actingAs($this->makeUser('superadmin'));

        $response = $this->getJson('/api/preschool/reports/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.dashboard.risk.total_at_risk_students', 0)
            ->assertJsonPath('data.dashboard.risk.attendance', 0)
            ->assertJsonPath('data.dashboard.risk.assessment', 0)
            ->assertJsonPath('data.dashboard.risk.health', 0)
            ->assertJsonPath('data.dashboard.risk.payments', 0)
            ->assertJsonPath('data.dashboard.risk.guardians', 0);
    }

    private function makeUser(string $roleCode): User
    {
        return User::query()->create([
            'name' => ucfirst(str_replace('-', ' ', $roleCode)),
            'email' => uniqid($roleCode.'_', true).'@example.test',
            'password' => 'password',
            'role_code' => $roleCode,
        ]);
    }
}
