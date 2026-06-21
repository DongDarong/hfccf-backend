<?php

namespace Tests\Feature;

use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_list_view_and_resolve_security_events(): void
    {
        Sanctum::actingAs($this->makeUser('superadmin'));

        $event = SecurityEvent::query()->create([
            'event_type' => 'failed_login',
            'severity' => 'high',
            'description' => 'Multiple failed logins',
        ]);

        $this->getJson('/api/governance/security-events')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $event->id);

        $this->getJson("/api/governance/security-events/{$event->id}")
            ->assertOk()
            ->assertJsonPath('data.securityEvent.id', $event->id);

        $this->postJson("/api/governance/security-events/{$event->id}/resolve")
            ->assertOk()
            ->assertJsonPath('data.securityEvent.resolvedBy', $event->resolved_by);
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
