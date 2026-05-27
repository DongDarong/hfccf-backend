<?php

namespace Tests\Feature;

use App\Models\PreschoolGuardian;
use App\Models\PreschoolGuardianGovernanceIssue;
use App\Models\PreschoolGuardianRemediationLog;
use App\Models\PreschoolStudent;
use App\Models\PreschoolStudentGuardian;
use App\Models\Role;
use App\Models\User;
use App\Support\PreschoolGuardianGovernanceStatus;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolGuardianGovernanceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    // ── Sync ──────────────────────────────────────────────────────────────────

    public function test_sync_creates_governance_issues_from_consistency_report(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-gov-001', 'gov001@hfccf.org');
        Sanctum::actingAs($admin);

        $student = $this->createStudent('PS-GOV-001', 'No', 'Guardian', 'Legacy', '+855 12 001 001');

        $beforeCount = PreschoolGuardianGovernanceIssue::query()->count();

        $this->postJson('/api/preschool/guardians/governance/sync')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'data' => ['created', 'updated', 'skipped', 'total']]);

        $this->assertGreaterThan($beforeCount, PreschoolGuardianGovernanceIssue::query()->count());

        $issue = PreschoolGuardianGovernanceIssue::query()
            ->where('issue_type', 'student_no_active_guardian')
            ->where('student_id', $student->id)
            ->first();

        $this->assertNotNull($issue);
        $this->assertSame(PreschoolGuardianGovernanceStatus::DETECTED, $issue->status);
        $this->assertSame('critical', $issue->severity);
    }

    public function test_duplicate_issues_aggregate_correctly_on_re_sync(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-gov-010', 'gov010@hfccf.org');
        Sanctum::actingAs($admin);

        $student = $this->createStudent('PS-GOV-010', 'Dup', 'Sync', 'Legacy', '+855 12 010 010');

        $this->postJson('/api/preschool/guardians/governance/sync')->assertOk();

        $first = PreschoolGuardianGovernanceIssue::query()
            ->where('issue_type', 'student_no_active_guardian')
            ->where('student_id', $student->id)
            ->first();

        $this->assertNotNull($first);
        $initialCount = $first->recurrence_count;

        $this->postJson('/api/preschool/guardians/governance/sync')->assertOk();

        $first->refresh();
        $this->assertSame($initialCount + 1, $first->recurrence_count);

        // Should not have created a second record for the same active issue
        $count = PreschoolGuardianGovernanceIssue::query()
            ->where('issue_key', $first->issue_key)
            ->whereIn('status', PreschoolGuardianGovernanceStatus::ACTIVE_STATES)
            ->count();

        $this->assertSame(1, $count);
    }

    public function test_recurring_issues_increment_recurrence_count_after_resolution(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-gov-020', 'gov020@hfccf.org');
        Sanctum::actingAs($admin);

        $student = $this->createStudent('PS-GOV-020', 'Recurring', 'Issue', 'Legacy', '+855 12 020 020');

        $this->postJson('/api/preschool/guardians/governance/sync')->assertOk();

        $issue = PreschoolGuardianGovernanceIssue::query()
            ->where('issue_type', 'student_no_active_guardian')
            ->where('student_id', $student->id)
            ->first();

        $this->assertNotNull($issue);

        $this->postJson("/api/preschool/guardians/governance/issues/{$issue->id}/resolve", [
            'notes' => 'Guardian added.',
        ])->assertOk();

        $issue->refresh();
        $this->assertSame(PreschoolGuardianGovernanceStatus::RESOLVED, $issue->status);

        // Now re-sync — should create a NEW issue with recurrence_count = 1
        $this->postJson('/api/preschool/guardians/governance/sync')->assertOk();

        $newIssue = PreschoolGuardianGovernanceIssue::query()
            ->where('issue_key', $issue->issue_key)
            ->whereIn('status', PreschoolGuardianGovernanceStatus::ACTIVE_STATES)
            ->first();

        $this->assertNotNull($newIssue);
        $this->assertGreaterThan(0, $newIssue->recurrence_count);
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function test_issue_acknowledge_transitions_status(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-gov-030', 'gov030@hfccf.org');
        Sanctum::actingAs($admin);

        $issue = $this->createGovernanceIssue('student_no_active_guardian', 'critical', PreschoolGuardianGovernanceStatus::DETECTED);

        $this->postJson("/api/preschool/guardians/governance/issues/{$issue->id}/acknowledge")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', PreschoolGuardianGovernanceStatus::ACKNOWLEDGED);

        $this->assertNotNull($issue->fresh()->acknowledged_at);
    }

    public function test_issue_assign_sets_assigned_user_and_transitions_status(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-gov-040', 'gov040@hfccf.org');
        Sanctum::actingAs($admin);

        $issue = $this->createGovernanceIssue('legacy_guardian_mismatch', 'warning', PreschoolGuardianGovernanceStatus::DETECTED);

        $this->postJson("/api/preschool/guardians/governance/issues/{$issue->id}/assign", [
            'assigned_to_user_id' => $admin->id,
            'notes' => 'Please investigate.',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', PreschoolGuardianGovernanceStatus::ASSIGNED);

        $this->assertSame($admin->id, (string) $issue->fresh()->assigned_to_user_id);
    }

    public function test_issue_resolve_closes_issue_with_notes(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-gov-050', 'gov050@hfccf.org');
        Sanctum::actingAs($admin);

        $issue = $this->createGovernanceIssue('archived_primary_relationship', 'warning', PreschoolGuardianGovernanceStatus::ACKNOWLEDGED);

        $this->postJson("/api/preschool/guardians/governance/issues/{$issue->id}/resolve", [
            'notes' => 'Primary flag cleared.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', PreschoolGuardianGovernanceStatus::RESOLVED);

        $fresh = $issue->fresh();
        $this->assertSame(PreschoolGuardianGovernanceStatus::RESOLVED, $fresh->status);
        $this->assertNotNull($fresh->resolved_at);
        $this->assertSame('Primary flag cleared.', $fresh->resolution_notes);
    }

    public function test_issue_dismiss_requires_notes_and_preserves_record(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-gov-060', 'gov060@hfccf.org');
        Sanctum::actingAs($admin);

        $issue = $this->createGovernanceIssue('guardian_without_students', 'info', PreschoolGuardianGovernanceStatus::DETECTED);
        $beforeCount = PreschoolGuardianGovernanceIssue::query()->count();

        $this->postJson("/api/preschool/guardians/governance/issues/{$issue->id}/dismiss", [
            'notes' => 'Guardian is a backup contact, not linked to students intentionally.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', PreschoolGuardianGovernanceStatus::DISMISSED);

        // Record preserved
        $this->assertSame($beforeCount, PreschoolGuardianGovernanceIssue::query()->count());
        $this->assertNotNull($issue->fresh()->dismissed_at);
    }

    public function test_dismiss_requires_notes(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-gov-061', 'gov061@hfccf.org');
        Sanctum::actingAs($admin);

        $issue = $this->createGovernanceIssue('guardian_without_students', 'info', PreschoolGuardianGovernanceStatus::DETECTED);

        $this->postJson("/api/preschool/guardians/governance/issues/{$issue->id}/dismiss")
            ->assertStatus(422);
    }

    public function test_resolved_issue_cannot_be_transitioned_again(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-gov-070', 'gov070@hfccf.org');
        Sanctum::actingAs($admin);

        $issue = $this->createGovernanceIssue('pickup_permission_issue', 'warning', PreschoolGuardianGovernanceStatus::RESOLVED);

        $this->postJson("/api/preschool/guardians/governance/issues/{$issue->id}/acknowledge")
            ->assertStatus(422);
    }

    // ── Stale detection ───────────────────────────────────────────────────────

    public function test_stale_issue_detection_works(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-gov-080', 'gov080@hfccf.org');
        Sanctum::actingAs($admin);

        $staleIssue = $this->createGovernanceIssue('student_no_active_guardian', 'critical', PreschoolGuardianGovernanceStatus::DETECTED);
        $staleIssue->update(['detected_at' => now()->subDays(5)]);

        $this->getJson('/api/preschool/guardians/governance/stale-issues')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'data', 'meta']);

        $staleIds = $this->getJson('/api/preschool/guardians/governance/stale-issues')
            ->json('data.*.id');

        $this->assertContains($staleIssue->id, $staleIds);
    }

    // ── Dashboard ─────────────────────────────────────────────────────────────

    public function test_governance_dashboard_metrics_return_correctly(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-gov-090', 'gov090@hfccf.org');
        Sanctum::actingAs($admin);

        $this->createGovernanceIssue('student_no_active_guardian', 'critical', PreschoolGuardianGovernanceStatus::DETECTED);
        $this->createGovernanceIssue('legacy_guardian_mismatch', 'warning', PreschoolGuardianGovernanceStatus::RESOLVED);

        $this->getJson('/api/preschool/guardians/governance/dashboard-summary')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'totalIssues',
                    'activeIssues',
                    'resolvedIssues',
                    'dismissedIssues',
                    'bySeverity',
                    'byPriority',
                    'byStatus',
                    'staleIssues',
                    'recurringIssues',
                    'unassignedIssues',
                    'criticalUnresolved',
                    'generatedAt',
                ],
            ]);
    }

    // ── Remediation logs remain immutable ─────────────────────────────────────

    public function test_remediation_logs_remain_immutable_during_governance(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-gov-100', 'gov100@hfccf.org');
        Sanctum::actingAs($admin);

        $logCount = PreschoolGuardianRemediationLog::query()->count();

        $this->postJson('/api/preschool/guardians/governance/sync')->assertOk();

        $issue = $this->createGovernanceIssue('guardian_without_students', 'info', PreschoolGuardianGovernanceStatus::DETECTED);

        $this->postJson("/api/preschool/guardians/governance/issues/{$issue->id}/resolve", [
            'notes' => 'Not a real issue.',
        ])->assertOk();

        // Governance actions do not write to remediation_logs
        $this->assertSame($logCount, PreschoolGuardianRemediationLog::query()->count());
    }

    // ── RBAC ──────────────────────────────────────────────────────────────────

    public function test_teacher_preschool_cannot_perform_governance_actions(): void
    {
        $teacher = $this->makeUserWithRole('teacher-preschool', 'psc-gov-110', 'gov110@hfccf.org');
        Sanctum::actingAs($teacher);

        $issue = $this->createGovernanceIssue('guardian_without_students', 'info', PreschoolGuardianGovernanceStatus::DETECTED);

        $this->postJson('/api/preschool/guardians/governance/sync')->assertForbidden();
        $this->postJson("/api/preschool/guardians/governance/issues/{$issue->id}/acknowledge")->assertForbidden();
        $this->postJson("/api/preschool/guardians/governance/issues/{$issue->id}/resolve", ['notes' => 'x'])->assertForbidden();
        $this->postJson("/api/preschool/guardians/governance/issues/{$issue->id}/dismiss", ['notes' => 'x'])->assertForbidden();
    }

    public function test_unauthenticated_request_blocked(): void
    {
        $this->getJson('/api/preschool/guardians/governance/issues')->assertUnauthorized();
        $this->getJson('/api/preschool/guardians/governance/dashboard-summary')->assertUnauthorized();
    }

    // ── Response shape ────────────────────────────────────────────────────────

    public function test_response_shape_is_consistent(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-gov-120', 'gov120@hfccf.org');
        Sanctum::actingAs($admin);

        $this->getJson('/api/preschool/guardians/governance/issues')
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'data', 'meta'])
            ->assertJsonPath('success', true);

        $this->getJson('/api/preschool/guardians/governance/dashboard-summary')
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'data'])
            ->assertJsonPath('success', true);
    }

    // ── Recurring issues list ─────────────────────────────────────────────────

    public function test_recurring_issues_list_returns_only_issues_with_recurrence(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-gov-130', 'gov130@hfccf.org');
        Sanctum::actingAs($admin);

        $recurring = $this->createGovernanceIssue('student_no_active_guardian', 'critical', PreschoolGuardianGovernanceStatus::DETECTED, recurrenceCount: 3);
        $this->createGovernanceIssue('guardian_without_students', 'info', PreschoolGuardianGovernanceStatus::DETECTED, recurrenceCount: 0);

        $ids = $this->getJson('/api/preschool/guardians/governance/recurring-issues')
            ->assertOk()
            ->json('data.*.id');

        $this->assertContains($recurring->id, $ids);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

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

        $rows = $role->permissions->map(static fn ($p) => [
            'user_id' => $user->id,
            'permission_code' => $p->code,
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

    private function createGovernanceIssue(
        string $issueType,
        string $severity,
        string $status,
        int $recurrenceCount = 0,
    ): PreschoolGuardianGovernanceIssue {
        return PreschoolGuardianGovernanceIssue::query()->create([
            'issue_type' => $issueType,
            'issue_key' => $issueType.'-test-'.uniqid(),
            'severity' => $severity,
            'priority' => 'medium',
            'status' => $status,
            'detected_at' => now(),
            'recurrence_count' => $recurrenceCount,
            'resolved_at' => $status === 'resolved' ? now() : null,
            'dismissed_at' => $status === 'dismissed' ? now() : null,
        ]);
    }
}
