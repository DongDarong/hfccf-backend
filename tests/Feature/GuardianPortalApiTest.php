<?php

namespace Tests\Feature;

use App\Models\PreschoolGuardian;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GuardianPortalApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_invite_and_revoke_guardian_portal_access(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'gpp-001', 'portal.admin001@hfccf.org');
        Sanctum::actingAs($admin);

        $guardian = PreschoolGuardian::query()->create($this->guardianData('Guardian One', '+855 12 101 101'));

        $invite = $this->postJson("/api/preschool/guardians/{$guardian->id}/portal/invite", [
            'email' => 'guardian.one@hfccf.org',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.account.guardianId', $guardian->id);

        $accountId = $invite->json('data.account.id');

        $this->postJson("/api/preschool/guardian-portal/{$accountId}/revoke")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.account.status', 'revoked');
    }

    public function test_teacher_cannot_invite_guardian_portal_access(): void
    {
        $teacher = $this->makeUserWithRole('teacher-preschool', 'gpp-002', 'portal.teacher002@hfccf.org');
        Sanctum::actingAs($teacher);

        $guardian = PreschoolGuardian::query()->create($this->guardianData('Guardian Two', '+855 12 202 202'));

        $this->postJson("/api/preschool/guardians/{$guardian->id}/portal/invite", [
            'email' => 'guardian.two@hfccf.org',
        ])->assertForbidden();
    }

    public function test_public_guardian_portal_endpoints_are_disabled(): void
    {
        $this->getJson('/api/guardian-portal/me')
            ->assertNotFound();

        $this->getJson('/api/guardian-portal/students')
            ->assertNotFound();

        $this->getJson('/api/guardian-portal/students/1')
            ->assertNotFound();

        $this->postJson('/api/guardian-portal/activate', [
            'token' => 'placeholder-token',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ])
            ->assertNotFound();
    }

    public function test_admin_can_revoke_guardian_portal_access_record(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'gpp-005', 'portal.admin005@hfccf.org');
        Sanctum::actingAs($admin);

        $guardian = PreschoolGuardian::query()->create($this->guardianData('Guardian Five', '+855 12 505 505'));

        $invite = $this->postJson("/api/preschool/guardians/{$guardian->id}/portal/invite", [
            'email' => 'guardian.five@hfccf.org',
        ])->assertCreated();

        $this->assertTrue((bool) $invite->json('data.activationDisabled'));
        $accountId = $invite->json('data.account.id');

        $this->postJson("/api/preschool/guardian-portal/{$accountId}/revoke")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.account.status', 'revoked');
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

    private function guardianData(string $name, string $phone): array
    {
        return [
            'full_name' => $name,
            'phone' => $phone,
            'secondary_phone' => '+855 12 111 333',
            'email' => 'guardian@example.com',
            'address' => 'Phnom Penh',
            'occupation' => 'Business',
            'national_id' => 'NID-001',
            'status' => 'active',
            'notes' => 'Created for portal test coverage.',
        ];
    }
}
