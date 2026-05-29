<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolGovernanceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_view_governance_diff_summary_and_integrity_review(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psg-100', 'preschool.governance100@hfccf.org');
        Sanctum::actingAs($admin);

        $this->getJson('/api/preschool/governance-diff/summary')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'overview',
                    'comparisonModes',
                    'severityBands',
                    'reviewActions',
                    'retentionReview',
                    'filters',
                ],
            ]);

        $this->getJson('/api/preschool/integrity-review')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'overview',
                    'warnings',
                    'mismatches',
                    'integrityWarnings',
                    'riskScore',
                    'riskLevel',
                    'timeline',
                    'retentionReview',
                    'reviewKey',
                    'reviewStatus',
                    'reviewTrail',
                ],
            ]);
    }

    private function makeUserWithRole(string $roleCode, string $id, string $email): User
    {
        $role = Role::query()->with('permissions')->findOrFail($roleCode);

        $user = User::query()->create([
            'id' => $id,
            'first_name' => ucfirst(str_replace('-', ' ', $roleCode)),
            'last_name' => 'User',
            'username' => $roleCode.'-'.$id,
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
