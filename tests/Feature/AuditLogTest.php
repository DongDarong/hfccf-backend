<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_list_and_view_audit_logs(): void
    {
        Sanctum::actingAs($this->makeUser('superadmin'));

        $log = AuditLog::query()->create([
            'event_type' => 'SETTINGS_UPDATED',
            'module' => 'preschool',
            'entity_type' => 'settings',
            'entity_id' => '1',
            'action' => 'update',
            'actor_name' => 'Super Admin',
            'actor_role' => 'superadmin',
            'created_at' => now(),
        ]);

        $this->getJson('/api/governance/audit-logs')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $log->id);

        $this->getJson("/api/governance/audit-logs/{$log->id}")
            ->assertOk()
            ->assertJsonPath('data.auditLog.id', $log->id);
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
