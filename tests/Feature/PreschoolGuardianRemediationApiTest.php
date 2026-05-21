<?php

namespace Tests\Feature;

use App\Models\PreschoolGuardian;
use App\Models\PreschoolGuardianRemediationLog;
use App\Models\PreschoolStudent;
use App\Models\PreschoolStudentGuardian;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolGuardianRemediationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    // --- mark-reviewed ---

    public function test_admin_can_mark_issue_reviewed_without_changing_data(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-rem-001', 'rem001@hfccf.org');
        Sanctum::actingAs($admin);

        $student = $this->createStudent('PS-REM-001', 'No', 'Guardian', 'Legacy A', '+855 12 000 001');
        $beforeCount = PreschoolGuardianRemediationLog::query()->count();

        $this->postJson('/api/preschool/guardians/remediation/mark-reviewed', [
            'issue_type' => 'student_no_active_guardian',
            'student_id' => $student->id,
            'notes' => 'Reviewed — will link guardian tomorrow.',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'data']);

        $this->assertSame($beforeCount + 1, PreschoolGuardianRemediationLog::query()->count());
        $this->assertSame($student->guardian_name, $student->fresh()->guardian_name);
    }

    // --- set-primary ---

    public function test_admin_can_set_primary_guardian_and_clears_other_primary_flags(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-rem-010', 'rem010@hfccf.org');
        Sanctum::actingAs($admin);

        $student = $this->createStudent('PS-REM-010', 'Multi', 'Primary', 'Legacy', '+855 12 010 010');
        $g1 = $this->createGuardian('Guardian Alpha', '+855 12 100 001', 'alpha@ex.com');
        $g2 = $this->createGuardian('Guardian Beta', '+855 12 100 002', 'beta@ex.com');

        $r1 = $this->linkRelationship($student, $g1, 1, true, 'active', $admin->id);
        $r2 = $this->linkRelationship($student, $g2, 2, true, 'active', $admin->id);

        $this->postJson('/api/preschool/guardians/remediation/set-primary', [
            'student_id' => $student->id,
            'relationship_id' => $r1->id,
            'notes' => 'Resolved multiple primary.',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue((bool) $r1->fresh()->is_primary);
        $this->assertFalse((bool) $r2->fresh()->is_primary);

        $log = PreschoolGuardianRemediationLog::query()->latest()->first();
        $this->assertSame('set_primary', $log->action);
        $this->assertNotNull($log->before_snapshot);
        $this->assertNotNull($log->after_snapshot);
    }

    public function test_set_primary_refuses_inactive_relationship(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-rem-011', 'rem011@hfccf.org');
        Sanctum::actingAs($admin);

        $student = $this->createStudent('PS-REM-011', 'Inactive', 'Set', 'Legacy', '+855 12 011 011');
        $g = $this->createGuardian('Inactive G', '+855 12 101 001', 'ig@ex.com');
        $rel = $this->linkRelationship($student, $g, 1, false, 'inactive', $admin->id);

        $this->postJson('/api/preschool/guardians/remediation/set-primary', [
            'student_id' => $student->id,
            'relationship_id' => $rel->id,
        ])->assertStatus(422);
    }

    // --- clear-invalid-primary ---

    public function test_admin_can_clear_invalid_primary_from_archived_relationship(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-rem-020', 'rem020@hfccf.org');
        Sanctum::actingAs($admin);

        $student = $this->createStudent('PS-REM-020', 'Archived', 'Primary', 'Legacy', '+855 12 020 020');
        $g = $this->createGuardian('Archived G', '+855 12 200 001', 'ag@ex.com');
        $rel = $this->linkRelationship($student, $g, 1, true, 'archived', $admin->id);

        $this->assertTrue((bool) $rel->is_primary);

        $this->postJson('/api/preschool/guardians/remediation/clear-invalid-primary', [
            'relationship_id' => $rel->id,
            'notes' => 'Cleared stale primary flag.',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertFalse((bool) $rel->fresh()->is_primary);

        $log = PreschoolGuardianRemediationLog::query()->latest()->first();
        $this->assertSame('clear_invalid_primary', $log->action);
    }

    public function test_clear_invalid_primary_refuses_active_relationship(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-rem-021', 'rem021@hfccf.org');
        Sanctum::actingAs($admin);

        $student = $this->createStudent('PS-REM-021', 'Active', 'Rel', 'Legacy', '+855 12 021 021');
        $g = $this->createGuardian('Active G', '+855 12 201 001', 'acg@ex.com');
        $rel = $this->linkRelationship($student, $g, 1, true, 'active', $admin->id);

        $this->postJson('/api/preschool/guardians/remediation/clear-invalid-primary', [
            'relationship_id' => $rel->id,
        ])->assertStatus(422);
    }

    // --- clear-invalid-emergency-contact ---

    public function test_admin_can_clear_invalid_emergency_flags_from_inactive_relationship(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-rem-030', 'rem030@hfccf.org');
        Sanctum::actingAs($admin);

        $student = $this->createStudent('PS-REM-030', 'Inactive', 'Emergency', 'Legacy', '+855 12 030 030');
        $g = $this->createGuardian('Emerg G', '+855 12 300 001', 'emg@ex.com');
        $rel = $this->linkRelationship($student, $g, 1, false, 'inactive', $admin->id, canPickup: true, emergencyPriority: 2);

        $this->postJson('/api/preschool/guardians/remediation/clear-invalid-emergency-contact', [
            'relationship_id' => $rel->id,
            'notes' => 'Cleared inactive emergency flags.',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $fresh = $rel->fresh();
        $this->assertFalse((bool) $fresh->can_pickup);
        $this->assertNull($fresh->emergency_priority);

        $log = PreschoolGuardianRemediationLog::query()->latest()->first();
        $this->assertSame('clear_invalid_emergency_contact', $log->action);
        $this->assertNotNull($log->before_snapshot);
        $this->assertNotNull($log->after_snapshot);
    }

    public function test_clear_invalid_emergency_refuses_active_relationship(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-rem-031', 'rem031@hfccf.org');
        Sanctum::actingAs($admin);

        $student = $this->createStudent('PS-REM-031', 'Active', 'Emergency', 'Legacy', '+855 12 031 031');
        $g = $this->createGuardian('ActEmg G', '+855 12 301 001', 'atemg@ex.com');
        $rel = $this->linkRelationship($student, $g, 1, true, 'active', $admin->id);

        $this->postJson('/api/preschool/guardians/remediation/clear-invalid-emergency-contact', [
            'relationship_id' => $rel->id,
        ])->assertStatus(422);
    }

    // --- reconcile-legacy-fields ---

    public function test_admin_can_reconcile_legacy_fields_from_normalized_guardian_data(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-rem-040', 'rem040@hfccf.org');
        Sanctum::actingAs($admin);

        $student = $this->createStudent('PS-REM-040', 'Legacy', 'Mismatch', 'Old Name', '+855 12 999 999');
        $g = $this->createGuardian('Normalized Name', '+855 12 400 001', 'norm@ex.com');
        $this->linkRelationship($student, $g, 1, true, 'active', $admin->id);

        $this->postJson('/api/preschool/guardians/remediation/reconcile-legacy-fields', [
            'student_id' => $student->id,
            'confirmed' => true,
            'notes' => 'Reconciled after guardian data normalization.',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $fresh = $student->fresh();
        $this->assertSame('Normalized Name', $fresh->guardian_name);
        $this->assertSame('+855 12 400 001', $fresh->guardian_phone);

        $log = PreschoolGuardianRemediationLog::query()->latest()->first();
        $this->assertSame('reconcile_legacy_fields', $log->action);
    }

    public function test_reconcile_legacy_fields_refuses_without_confirmed_flag(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-rem-041', 'rem041@hfccf.org');
        Sanctum::actingAs($admin);

        $student = $this->createStudent('PS-REM-041', 'Legacy', 'Noconfirm', 'Old', '+855 12 041 041');

        $this->postJson('/api/preschool/guardians/remediation/reconcile-legacy-fields', [
            'student_id' => $student->id,
        ])->assertStatus(422);
    }

    public function test_duplicate_remediation_never_auto_merges_guardians(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-rem-050', 'rem050@hfccf.org');
        Sanctum::actingAs($admin);

        $student = $this->createStudent('PS-REM-050', 'Dup', 'Test', 'Legacy', '+855 12 050 050');
        $g1 = $this->createGuardian('Same Name', '+855 12 500 001', 'dup1@ex.com');
        $g2 = $this->createGuardian('Same Name', '+855 12 500 001', 'dup2@ex.com');
        $r1 = $this->linkRelationship($student, $g1, 1, true, 'active', $admin->id);
        $this->linkRelationship($student, $g2, 2, false, 'active', $admin->id);

        $beforeGuardianCount = PreschoolGuardian::query()->count();

        $this->postJson('/api/preschool/guardians/remediation/archive-duplicate-candidate', [
            'relationship_id' => $r1->id,
            'notes' => 'Archiving duplicate relationship only.',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame($beforeGuardianCount, PreschoolGuardian::query()->count());
        $this->assertSame('archived', $r1->fresh()->status);
    }

    // --- archive-orphan-guardian ---

    public function test_admin_can_archive_orphan_guardian_with_confirmation(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-rem-060', 'rem060@hfccf.org');
        Sanctum::actingAs($admin);

        $g = $this->createGuardian('Orphan G', '+855 12 600 001', 'orphan@ex.com');

        $this->postJson('/api/preschool/guardians/remediation/archive-orphan-guardian', [
            'guardian_id' => $g->id,
            'confirmed' => true,
            'notes' => 'No students linked.',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('archived', $g->fresh()->status);
    }

    public function test_archive_orphan_refuses_guardian_with_relationships(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-rem-061', 'rem061@hfccf.org');
        Sanctum::actingAs($admin);

        $student = $this->createStudent('PS-REM-061', 'Has', 'Guardian', 'Legacy', '+855 12 061 061');
        $g = $this->createGuardian('Linked G', '+855 12 601 001', 'linked@ex.com');
        $this->linkRelationship($student, $g, 1, true, 'active', $admin->id);

        $this->postJson('/api/preschool/guardians/remediation/archive-orphan-guardian', [
            'guardian_id' => $g->id,
            'confirmed' => true,
        ])->assertStatus(422);
    }

    // --- RBAC ---

    public function test_teacher_preschool_cannot_perform_remediation_actions(): void
    {
        $teacher = $this->makeUserWithRole('teacher-preschool', 'psc-rem-070', 'rem070@hfccf.org');
        Sanctum::actingAs($teacher);

        $this->postJson('/api/preschool/guardians/remediation/mark-reviewed', [
            'issue_type' => 'student_no_active_guardian',
        ])->assertForbidden();

        $this->postJson('/api/preschool/guardians/remediation/set-primary', [
            'student_id' => 1,
            'relationship_id' => 1,
        ])->assertForbidden();

        $this->postJson('/api/preschool/guardians/remediation/clear-invalid-primary', [
            'relationship_id' => 1,
        ])->assertForbidden();

        $this->postJson('/api/preschool/guardians/remediation/clear-invalid-emergency-contact', [
            'relationship_id' => 1,
        ])->assertForbidden();
    }

    public function test_unauthenticated_request_blocked(): void
    {
        $this->getJson('/api/preschool/guardians/remediation/logs')
            ->assertUnauthorized();
    }

    // --- logs ---

    public function test_remediation_log_stores_before_and_after_snapshots(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-rem-080', 'rem080@hfccf.org');
        Sanctum::actingAs($admin);

        $student = $this->createStudent('PS-REM-080', 'Snap', 'Shot', 'Old Name', '+855 12 080 080');
        $g = $this->createGuardian('New Name', '+855 12 800 001', 'snap@ex.com');
        $this->linkRelationship($student, $g, 1, true, 'active', $admin->id);

        $this->postJson('/api/preschool/guardians/remediation/reconcile-legacy-fields', [
            'student_id' => $student->id,
            'confirmed' => true,
        ])->assertOk();

        $log = PreschoolGuardianRemediationLog::query()->latest()->first();

        $this->assertNotNull($log->before_snapshot);
        $this->assertNotNull($log->after_snapshot);
        $this->assertArrayHasKey('guardianName', $log->before_snapshot);
        $this->assertArrayHasKey('guardianName', $log->after_snapshot);
    }

    public function test_admin_can_retrieve_remediation_logs(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-rem-090', 'rem090@hfccf.org');
        Sanctum::actingAs($admin);

        $this->getJson('/api/preschool/guardians/remediation/logs')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success', 'message',
                'data',
                'meta' => ['currentPage', 'lastPage', 'perPage', 'total'],
            ]);
    }

    public function test_response_shape_is_consistent(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-rem-100', 'rem100@hfccf.org');
        Sanctum::actingAs($admin);

        $student = $this->createStudent('PS-REM-100', 'Shape', 'Test', 'Legacy', '+855 12 100 100');

        $this->postJson('/api/preschool/guardians/remediation/mark-reviewed', [
            'issue_type' => 'student_no_active_guardian',
            'student_id' => $student->id,
        ])
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'data'])
            ->assertJsonPath('success', true);
    }

    // --- helpers ---

    private function makeUserWithRole(string $roleCode, string|int $id, string $email): User
    {
        $role = Role::query()->where('code', $roleCode)->firstOrFail();

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

    private function createGuardian(string $name, string $phone, string $email): PreschoolGuardian
    {
        return PreschoolGuardian::query()->create([
            'full_name' => $name,
            'phone' => $phone,
            'email' => $email,
            'status' => 'active',
        ]);
    }

    private function linkRelationship(
        PreschoolStudent $student,
        PreschoolGuardian $guardian,
        int $priority,
        bool $primary,
        string $status,
        string|int|null $adminId = null,
        bool $canPickup = true,
        ?int $emergencyPriority = null,
    ): PreschoolStudentGuardian {
        return PreschoolStudentGuardian::query()->create([
            'student_id' => $student->id,
            'guardian_id' => $guardian->id,
            'relationship_type' => 'guardian',
            'is_primary' => $primary,
            'can_pickup' => $canPickup,
            'emergency_priority' => $emergencyPriority ?? $priority,
            'status' => $status,
            'starts_at' => now()->toDateString(),
            'created_by_user_id' => $adminId,
            'updated_by_user_id' => $adminId,
        ]);
    }
}
