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
            ->assertJsonPath('data.dashboard.health.incident_categories_count', 6);
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
