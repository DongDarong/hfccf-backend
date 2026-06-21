<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolSettingsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_returns_health_summary(): void
    {
        Sanctum::actingAs($this->makeUser('superadmin'));

        $response = $this->getJson('/api/preschool/settings/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.dashboard.health.critical_alert_enabled', true)
            ->assertJsonPath('data.dashboard.health.severity_levels_count', 4)
            ->assertJsonPath('data.dashboard.health.incident_categories_count', 6)
            ->assertJsonPath('data.dashboard.payments.fee_types_count', 6)
            ->assertJsonPath('data.dashboard.payments.payment_methods_count', 5)
            ->assertJsonPath('data.dashboard.payments.late_fee_enabled', true)
            ->assertJsonPath('data.dashboard.preferences.minimum_enrollment_age_months', 24)
            ->assertJsonPath('data.dashboard.preferences.student_code_prefix', 'PS')
            ->assertJsonPath('data.dashboard.preferences.default_class_capacity', 18)
            ->assertJsonPath('data.dashboard.preferences.enrollment_notification_enabled', true);
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
