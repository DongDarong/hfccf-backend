<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GovernanceDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_view_governance_dashboard(): void
    {
        Sanctum::actingAs($this->makeUser('superadmin'));

        AuditLog::query()->create([
            'event_type' => 'SETTINGS_UPDATED',
            'module' => 'preschool',
            'action' => 'update',
            'actor_name' => 'Super Admin',
            'actor_role' => 'superadmin',
            'created_at' => now(),
        ]);

        SecurityEvent::query()->create([
            'event_type' => 'failed_login',
            'severity' => 'critical',
            'description' => 'Repeated login failures',
        ]);

        $response = $this->getJson('/api/governance/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.dashboard.security.active_security_events', 1)
            ->assertJsonPath('data.dashboard.audit.audit_events_today', 1)
            ->assertJsonPath('data.dashboard.configuration.changes_today', 1);
    }

    public function test_adminpreschool_is_forbidden(): void
    {
        Sanctum::actingAs($this->makeUser('adminpreschool'));

        $this->getJson('/api/governance/dashboard')->assertForbidden();
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
