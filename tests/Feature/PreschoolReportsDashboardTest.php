<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolReportsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_reports_block_contains_live_kpi_shape(): void
    {
        Sanctum::actingAs($this->makeUser('superadmin'));

        $response = $this->getJson('/api/preschool/reports/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.dashboard.cards.0.title', 'Students')
            ->assertJsonPath('data.dashboard.cards.1.title', 'Attendance')
            ->assertJsonPath('data.dashboard.cards.2.title', 'Assessment')
            ->assertJsonPath('data.dashboard.cards.3.title', 'Health')
            ->assertJsonPath('data.dashboard.cards.4.title', 'Payments')
            ->assertJsonPath('data.dashboard.cards.5.title', 'Governance');
    }

    public function test_teacher_cannot_view_executive_dashboard(): void
    {
        Sanctum::actingAs($this->makeUser('teacher-preschool'));

        $this->getJson('/api/preschool/reports/dashboard')->assertForbidden();
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
