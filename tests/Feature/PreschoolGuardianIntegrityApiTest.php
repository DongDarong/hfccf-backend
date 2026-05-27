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

class PreschoolGuardianIntegrityApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_duplicate_guardian_detection_reports_candidates_without_merging_records(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-int-100', 'preschool.integrity100@hfccf.org');
        Sanctum::actingAs($admin);

        PreschoolGuardian::query()->create($this->guardianData('Sok Chan', '+855 12 111 111', 'dup1@example.com'));
        PreschoolGuardian::query()->create($this->guardianData('Sok Chan', '+855 12 111 111', 'dup2@example.com'));

        $beforeCount = PreschoolGuardian::query()->count();

        $this->getJson('/api/preschool/guardians/duplicates')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'summary',
                    'items',
                    'generatedAt',
                ],
            ])
            ->assertJsonPath('data.summary.candidateGroups', 1)
            ->assertJsonCount(1, 'data.items');

        $this->assertSame($beforeCount, PreschoolGuardian::query()->count());
    }

    public function test_consistency_report_detects_student_and_guardian_relationship_drift(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-int-110', 'preschool.integrity110@hfccf.org');
        Sanctum::actingAs($admin);

        $studentWithoutGuardian = $this->createStudent('PS-INT-110', 'No', 'Guardian', 'Legacy A', '+855 12 000 000');

        $studentMultiplePrimary = $this->createStudent('PS-INT-111', 'Multi', 'Primary', 'Legacy B', '+855 12 000 001');
        $guardianOne = PreschoolGuardian::query()->create($this->guardianData('Guardian One', '+855 12 222 222', 'g1@example.com'));
        $guardianTwo = PreschoolGuardian::query()->create($this->guardianData('Guardian Two', '+855 12 333 333', 'g2@example.com'));
        $this->linkRelationship($studentMultiplePrimary, $guardianOne, 1, true, 'active', adminId: $admin->id);
        $this->linkRelationship($studentMultiplePrimary, $guardianTwo, 2, true, 'active', adminId: $admin->id);

        $studentArchivedPrimary = $this->createStudent('PS-INT-112', 'Archived', 'Primary', 'Legacy C', '+855 12 000 002');
        $guardianThree = PreschoolGuardian::query()->create($this->guardianData('Guardian Three', '+855 12 444 444', 'g3@example.com'));
        $this->linkRelationship($studentArchivedPrimary, $guardianThree, 1, true, 'archived', adminId: $admin->id);

        $studentInactiveEmergency = $this->createStudent('PS-INT-113', 'Inactive', 'Emergency', 'Legacy D', '+855 12 000 003');
        $guardianFour = PreschoolGuardian::query()->create($this->guardianData('Guardian Four', '+855 12 555 555', 'g4@example.com'));
        $this->linkRelationship($studentInactiveEmergency, $guardianFour, 1, false, 'inactive', true, adminId: $admin->id);

        $studentLegacyMismatch = $this->createStudent('PS-INT-114', 'Legacy', 'Mismatch', 'Legacy Name', '+855 12 999 000');
        $guardianFive = PreschoolGuardian::query()->create($this->guardianData('Normalized Name', '+855 12 888 000', 'g5@example.com'));
        $this->linkRelationship($studentLegacyMismatch, $guardianFive, 1, true, 'active', adminId: $admin->id);

        $studentPickupIssue = $this->createStudent('PS-INT-115', 'Pickup', 'Issue', 'Legacy E', '+855 12 000 004');
        $guardianSix = PreschoolGuardian::query()->create($this->guardianData('Guardian Six', '+855 12 666 666', 'g6@example.com'));
        $this->linkRelationship($studentPickupIssue, $guardianSix, 1, false, 'active', false, adminId: $admin->id);

        $guardianWithoutStudents = PreschoolGuardian::query()->create($this->guardianData('Orphan Guardian', '+855 12 777 777', 'g7@example.com'));
        $this->assertDatabaseHas('preschool_guardians', ['id' => $guardianWithoutStudents->id]);

        $response = $this->getJson('/api/preschool/guardians/consistency-report')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'summary',
                    'issues',
                    'generatedAt',
                ],
            ]);

        $data = $response->json('data');

        $this->assertGreaterThanOrEqual(1, $data['summary']['studentsWithoutActiveGuardian']);
        $this->assertGreaterThanOrEqual(1, $data['summary']['multiplePrimaryGuardianStudents']);
        $this->assertGreaterThanOrEqual(1, $data['summary']['guardiansWithoutStudents']);
        $this->assertGreaterThanOrEqual(1, $data['summary']['archivedPrimaryRelationships']);
        $this->assertGreaterThanOrEqual(1, $data['summary']['inactiveEmergencyContacts']);
        $this->assertGreaterThanOrEqual(1, $data['summary']['pickupPermissionIssues']);
        $this->assertGreaterThanOrEqual(1, $data['summary']['legacyMismatches']);
        $this->assertGreaterThanOrEqual(2, $data['summary']['criticalCount']);
        $this->assertGreaterThanOrEqual(4, $data['summary']['warningCount']);
        $this->assertGreaterThanOrEqual(1, $data['summary']['infoCount']);
        $this->assertGreaterThanOrEqual(7, $data['summary']['issueCount']);

        $issues = collect($data['issues']);

        $this->assertTrue($issues->contains(fn (array $issue): bool => $issue['type'] === 'student_no_active_guardian'));
        $this->assertTrue($issues->contains(fn (array $issue): bool => $issue['type'] === 'multiple_active_primary_guardians'));
        $this->assertTrue($issues->contains(fn (array $issue): bool => $issue['type'] === 'archived_primary_relationship'));
        $this->assertTrue($issues->contains(fn (array $issue): bool => $issue['type'] === 'inactive_emergency_contact'));
        $this->assertTrue($issues->contains(fn (array $issue): bool => $issue['type'] === 'pickup_permission_issue'));
        $this->assertTrue($issues->contains(fn (array $issue): bool => $issue['type'] === 'legacy_guardian_mismatch'));

        $legacyIssue = $issues->firstWhere('type', 'legacy_guardian_mismatch');
        $this->assertSame('normalized', $legacyIssue['student']['guardianSource'] ?? null);
        $this->assertNotEmpty($legacyIssue['difference'] ?? []);
    }

    public function test_unauthorized_roles_are_blocked_from_integrity_reports(): void
    {
        $teacher = $this->makeUserWithRole('teacher-preschool', 'psc-int-120', 'preschool.integrity120@hfccf.org');
        Sanctum::actingAs($teacher);

        $this->getJson('/api/preschool/guardians/duplicates')->assertForbidden();
        $this->getJson('/api/preschool/guardians/consistency-report')->assertForbidden();
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

    private function createStudent(string $code, string $firstName, string $lastName, string $legacyName, string $legacyPhone): PreschoolStudent
    {
        return PreschoolStudent::query()->create([
            'student_code' => $code,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'gender' => 'other',
            'date_of_birth' => now()->subYears(4)->toDateString(),
            'guardian_name' => $legacyName,
            'guardian_phone' => $legacyPhone,
            'address' => 'Phnom Penh',
            'status' => 'active',
        ]);
    }

    private function guardianData(string $name, string $phone, string $email): array
    {
        return [
            'full_name' => $name,
            'phone' => $phone,
            'secondary_phone' => null,
            'email' => $email,
            'address' => 'Phnom Penh',
            'occupation' => 'Business',
            'national_id' => 'NID-001',
            'status' => 'active',
            'notes' => 'Created for integrity coverage.',
        ];
    }

    private function linkRelationship(
        PreschoolStudent $student,
        PreschoolGuardian $guardian,
        int $priority,
        bool $primary,
        string $status,
        bool $pickup = true,
        ?string $adminId = null
    ): void {
        PreschoolStudentGuardian::query()->create([
            'student_id' => $student->id,
            'guardian_id' => $guardian->id,
            'relationship_type' => 'guardian',
            'is_primary' => $primary,
            'can_pickup' => $pickup,
            'emergency_priority' => $priority,
            'status' => $status,
            'starts_at' => now()->toDateString(),
            'notes' => 'Seeded for integrity coverage.',
            'created_by_user_id' => $adminId,
            'updated_by_user_id' => $adminId,
        ]);
    }
}
