<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GovernancePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_superadmins_are_forbidden(): void
    {
        Sanctum::actingAs($this->makeUser('adminpreschool'));

        $this->getJson('/api/governance/dashboard')->assertForbidden();
        $this->getJson('/api/governance/audit-logs')->assertForbidden();
        $this->getJson('/api/governance/security-events')->assertForbidden();
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
