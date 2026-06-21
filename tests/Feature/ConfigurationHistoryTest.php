<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConfigurationHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_view_configuration_history(): void
    {
        Sanctum::actingAs($this->makeUser('superadmin'));

        $log = AuditLog::query()->create([
            'event_type' => 'SETTINGS_UPDATED',
            'module' => 'preschool',
            'entity_type' => 'settings',
            'action' => 'update',
            'actor_name' => 'Super Admin',
            'actor_role' => 'superadmin',
            'before_state' => ['late_threshold_minutes' => 15],
            'after_state' => ['late_threshold_minutes' => 20],
            'created_at' => now(),
        ]);

        $this->getJson('/api/governance/configuration-history')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $log->id)
            ->assertJsonPath('data.items.0.afterState.late_threshold_minutes', 20);
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
