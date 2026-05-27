<?php

namespace Tests\Feature;

use App\Models\PreschoolGuardian;
use App\Models\PreschoolStudent;
use App\Models\PreschoolStudentGuardian;
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

    public function test_guardian_can_view_linked_active_student_but_not_unrelated_student(): void
    {
        $portalUser = $this->activateGuardianPortal('Guardian Three', '+855 12 303 303', 'guardian.three@hfccf.org');
        Sanctum::actingAs($portalUser);

        $student = $this->createStudent('GPP-STU-001', 'Link', 'Student');
        $this->linkRelationship($student, $portalUser->guardianPortalAccount()->first()->guardian, 1, true, $portalUser->id);

        $otherStudent = $this->createStudent('GPP-STU-002', 'Other', 'Student');

        $this->getJson('/api/guardian-portal/me')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson('/api/guardian-portal/students')
            ->assertOk()
            ->assertJsonCount(1, 'data.items');

        $this->getJson("/api/guardian-portal/students/{$student->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson("/api/guardian-portal/students/{$otherStudent->id}")
            ->assertForbidden();
    }

    public function test_inactive_relationship_blocks_portal_visibility(): void
    {
        $portalUser = $this->activateGuardianPortal('Guardian Four', '+855 12 404 404', 'guardian.four@hfccf.org');
        Sanctum::actingAs($portalUser);

        $student = $this->createStudent('GPP-STU-010', 'Inactive', 'Link');
        $this->linkRelationship($student, $portalUser->guardianPortalAccount()->first()->guardian, 1, true, $portalUser->id, 'inactive');

        $this->getJson('/api/guardian-portal/students')
            ->assertOk()
            ->assertJsonCount(0, 'data.items');

        $this->getJson("/api/guardian-portal/students/{$student->id}")
            ->assertForbidden();
    }

    public function test_revoked_account_blocks_portal_access(): void
    {
        $portalUser = $this->activateGuardianPortal('Guardian Five', '+855 12 505 505', 'guardian.five@hfccf.org');
        $account = $portalUser->guardianPortalAccount()->first();

        $admin = $this->makeUserWithRole('adminpreschool', 'gpp-005', 'portal.admin005@hfccf.org');
        Sanctum::actingAs($admin);
        $this->postJson("/api/preschool/guardian-portal/{$account->id}/revoke")->assertOk();

        Sanctum::actingAs($portalUser);
        $this->getJson('/api/guardian-portal/me')->assertForbidden();
    }

    public function test_guardian_cannot_access_admin_portal_routes(): void
    {
        $portalUser = $this->activateGuardianPortal('Guardian Six', '+855 12 606 606', 'guardian.six@hfccf.org');
        Sanctum::actingAs($portalUser);

        $this->getJson('/api/preschool/guardian-portal/accounts')
            ->assertForbidden();
    }

    private function activateGuardianPortal(string $guardianName, string $guardianPhone, string $email): User
    {
        $guardian = PreschoolGuardian::query()->create($this->guardianData($guardianName, $guardianPhone));

        $invite = $this->makeUserWithRole('adminpreschool', 'gpp-invite-'.substr(md5($guardianName), 0, 6), 'invite.'.$email);
        Sanctum::actingAs($invite);

        $response = $this->postJson("/api/preschool/guardians/{$guardian->id}/portal/invite", [
            'email' => $email,
        ])->assertCreated();

        $activationToken = $response->json('data.activationToken');

        $activation = $this->postJson('/api/guardian-portal/activate', [
            'token' => $activationToken,
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ])->assertOk();

        return User::query()->findOrFail($activation->json('data.user.id'));
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

    private function createStudent(string $code, string $firstName, string $lastName): PreschoolStudent
    {
        return PreschoolStudent::query()->create([
            'student_code' => $code,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'gender' => 'other',
            'date_of_birth' => now()->subYears(4)->toDateString(),
            'guardian_name' => 'Legacy Guardian',
            'guardian_phone' => '+855 12 777 777',
            'address' => 'Phnom Penh',
            'status' => 'active',
        ]);
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

    private function linkRelationship(PreschoolStudent $student, PreschoolGuardian $guardian, int $priority, bool $primary, string $userId, string $status = 'active'): void
    {
        PreschoolStudentGuardian::query()->create([
            'student_id' => $student->id,
            'guardian_id' => $guardian->id,
            'relationship_type' => 'guardian',
            'is_primary' => $primary,
            'can_pickup' => true,
            'emergency_priority' => $priority,
            'status' => $status,
            'starts_at' => now()->toDateString(),
            'notes' => 'Seeded for portal test coverage.',
            'created_by_user_id' => $userId,
            'updated_by_user_id' => $userId,
        ]);
    }
}
