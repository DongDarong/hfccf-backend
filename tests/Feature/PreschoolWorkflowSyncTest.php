<?php

namespace Tests\Feature;

use App\Models\PreschoolEnrollmentApplication;
use App\Models\PreschoolHealthAlert;
use App\Models\PreschoolInvoice;
use App\Models\PreschoolStudent;
use App\Models\PreschoolWorkflowDefinition;
use App\Models\PreschoolWorkflowInstance;
use App\Models\PreschoolWorkflowSyncRun;
use App\Models\Role;
use App\Models\User;
use App\Services\PreschoolWorkflowService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolWorkflowSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_preview_does_not_create_workflow_instances(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'sync-admin-001', 'sync.admin.001@hfccf.org');
        Sanctum::actingAs($admin);

        $application = $this->createEnrollmentApplication('ENR-SYNC-001', 'submitted');

        $response = $this->getJson('/api/preschool/workflows/sync/preview?definition_key=enrollment_admission&source_type=preschool_enrollment_application&status=submitted&limit=10');

        $response->assertOk()
            ->assertJsonPath('data.summary.eligible', 1)
            ->assertJsonPath('data.summary.created', 1)
            ->assertJsonPath('data.summary.existing', 0)
            ->assertJsonPath('data.summary.skipped', 0)
            ->assertJsonPath('data.summary.failed', 0)
            ->assertJsonPath('data.items.0.status', 'created');

        $this->assertSame(0, PreschoolWorkflowSyncRun::query()->count());
        $this->assertSame(0, PreschoolWorkflowInstance::query()->count());
        $this->assertSame('submitted', $application->fresh()->status);
    }

    public function test_run_creates_workflow_instances_for_eligible_enrollment_records_and_is_idempotent(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'sync-admin-002', 'sync.admin.002@hfccf.org');
        Sanctum::actingAs($admin);

        $first = $this->createEnrollmentApplication('ENR-SYNC-002', 'submitted');
        $second = $this->createEnrollmentApplication('ENR-SYNC-003', 'submitted');

        $payload = [
            'definition_key' => 'enrollment_admission',
            'source_type' => 'preschool_enrollment_application',
            'status' => 'submitted',
            'limit' => 10,
        ];

        $firstRun = $this->postJson('/api/preschool/workflows/sync/run', $payload);
        $firstRun->assertOk()
            ->assertJsonPath('data.summary.eligible', 2)
            ->assertJsonPath('data.summary.created', 2)
            ->assertJsonPath('data.summary.existing', 0)
            ->assertJsonPath('data.summary.skipped', 0)
            ->assertJsonPath('data.summary.failed', 0)
            ->assertJsonPath('data.run.mode', 'run')
            ->assertJsonPath('data.run.status', 'completed')
            ->assertJsonPath('data.run.processedCount', 2)
            ->assertJsonPath('data.run.startedByUserId', $admin->id);

        $this->assertNotEmpty($firstRun->json('data.run.startedAt'));
        $this->assertNotEmpty($firstRun->json('data.run.completedAt'));

        $this->assertSame(2, PreschoolWorkflowInstance::query()->count());
        $this->assertSame(1, PreschoolWorkflowSyncRun::query()->count());
        $this->assertSame(2, PreschoolWorkflowSyncRun::query()->firstOrFail()->items()->count());

        $secondRun = $this->postJson('/api/preschool/workflows/sync/run', $payload);
        $secondRun->assertOk()
            ->assertJsonPath('data.summary.eligible', 2)
            ->assertJsonPath('data.summary.created', 0)
            ->assertJsonPath('data.summary.existing', 2)
            ->assertJsonPath('data.summary.skipped', 0)
            ->assertJsonPath('data.summary.failed', 0)
            ->assertJsonPath('data.run.status', 'completed')
            ->assertJsonPath('data.run.startedByUserId', $admin->id);

        $this->assertSame(2, PreschoolWorkflowInstance::query()->count());
        $this->assertSame(2, PreschoolWorkflowSyncRun::query()->count());
        $this->assertSame('submitted', $first->fresh()->status);
        $this->assertSame('submitted', $second->fresh()->status);
    }

    public function test_run_respects_limit(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'sync-admin-003', 'sync.admin.003@hfccf.org');
        Sanctum::actingAs($admin);

        $this->createEnrollmentApplication('ENR-SYNC-004', 'submitted');
        $this->createEnrollmentApplication('ENR-SYNC-005', 'submitted');
        $this->createEnrollmentApplication('ENR-SYNC-006', 'submitted');

        $response = $this->postJson('/api/preschool/workflows/sync/run', [
            'definition_key' => 'enrollment_admission',
            'source_type' => 'preschool_enrollment_application',
            'status' => 'submitted',
            'limit' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.summary.eligible', 1)
            ->assertJsonPath('data.summary.created', 1);

        $this->assertSame(1, PreschoolWorkflowInstance::query()->count());
    }

    public function test_run_enforces_maximum_batch_size(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'sync-admin-003b', 'sync.admin.003b@hfccf.org');
        Sanctum::actingAs($admin);

        $this->createEnrollmentApplication('ENR-SYNC-004B', 'submitted');
        $this->createEnrollmentApplication('ENR-SYNC-005B', 'submitted');

        $response = $this->postJson('/api/preschool/workflows/sync/run', [
            'definition_key' => 'enrollment_admission',
            'source_type' => 'preschool_enrollment_application',
            'status' => 'submitted',
            'limit' => 2,
            'batch_size' => 500,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.run.batchSize', 100)
            ->assertJsonPath('data.run.processedCount', 2);
    }

    public function test_run_creates_workflow_instances_for_eligible_health_alerts(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'sync-admin-004', 'sync.admin.004@hfccf.org');
        Sanctum::actingAs($admin);

        $student = $this->createStudent('STU-SYNC-001', 'Health', 'Student');
        $alert = PreschoolHealthAlert::query()->create([
            'student_id' => $student->id,
            'alert_type' => 'critical_incident',
            'severity' => 'critical',
            'status' => 'new',
            'title' => 'Health alert sync',
            'description' => 'Health alert for workflow sync.',
            'source_type' => 'health_incident',
            'source_id' => 'incident-001',
        ]);

        $response = $this->postJson('/api/preschool/workflows/sync/run', [
            'definition_key' => 'health_alert_resolution',
            'source_type' => 'preschool_health_alert',
            'status' => 'new',
            'limit' => 10,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.summary.eligible', 1)
            ->assertJsonPath('data.summary.created', 1)
            ->assertJsonPath('data.items.0.sourceId', (string) $alert->id)
            ->assertJsonPath('data.run.status', 'completed')
            ->assertJsonPath('data.run.startedByUserId', $admin->id);

        $this->assertSame(1, PreschoolWorkflowInstance::query()->count());
        $this->assertSame(1, PreschoolWorkflowSyncRun::query()->count());
        $this->assertSame('new', $alert->fresh()->status);
    }

    public function test_run_creates_workflow_instances_for_eligible_invoices(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'sync-admin-005', 'sync.admin.005@hfccf.org');
        Sanctum::actingAs($admin);

        $student = $this->createStudent('STU-SYNC-002', 'Invoice', 'Student');
        $class = \App\Models\PreschoolClass::query()->create([
            'code' => 'CLS-SYNC-001',
            'name' => 'Sync Class',
            'level' => 'Level A',
            'status' => 'active',
            'students_count' => 0,
            'tuition_fee' => 100,
        ]);
        $academicYear = \App\Models\PreschoolAcademicYear::query()->create([
            'code' => 'AY-SYNC-001',
            'label' => '2026-2027',
            'start_date' => today()->startOfYear()->toDateString(),
            'end_date' => today()->endOfYear()->toDateString(),
            'status' => 'active',
            'is_current' => true,
        ]);
        $term = \App\Models\PreschoolAcademicTerm::query()->create([
            'academic_year_id' => $academicYear->id,
            'code' => 'TERM-SYNC-001',
            'name' => 'Term 1',
            'start_date' => today()->startOfYear()->toDateString(),
            'end_date' => today()->addMonths(3)->toDateString(),
            'status' => 'active',
            'is_current' => true,
            'sort_order' => 1,
        ]);
        $invoice = PreschoolInvoice::query()->create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'academic_year_id' => $academicYear->id,
            'term_id' => $term->id,
            'invoice_number' => 'INV-SYNC-001',
            'issue_date' => today()->toDateString(),
            'due_date' => today()->addDays(7)->toDateString(),
            'subtotal' => 100,
            'discount_amount' => 0,
            'total_amount' => 100,
            'paid_amount' => 0,
            'balance_due' => 100,
            'status' => 'cancelled',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $response = $this->postJson('/api/preschool/workflows/sync/run', [
            'definition_key' => 'invoice_collection',
            'source_type' => 'preschool_invoice',
            'status' => 'cancelled',
            'limit' => 10,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.summary.eligible', 1)
            ->assertJsonPath('data.summary.created', 1)
            ->assertJsonPath('data.items.0.sourceId', (string) $invoice->id)
            ->assertJsonPath('data.run.status', 'completed')
            ->assertJsonPath('data.run.startedByUserId', $admin->id);

        $this->assertSame(1, PreschoolWorkflowInstance::query()->count());
        $this->assertSame(1, PreschoolWorkflowSyncRun::query()->count());
        $this->assertSame('cancelled', $invoice->fresh()->status);
    }

    public function test_teacher_cannot_access_sync_endpoints(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'sync-admin-teacher-gate', 'sync.admin.teacher.gate@hfccf.org');
        $run = PreschoolWorkflowSyncRun::query()->create([
            'mode' => 'run',
            'status' => 'completed',
            'started_by_user_id' => $admin->id,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $teacher = $this->makeUserWithRole('teacher-preschool', 'sync-teacher-001', 'sync.teacher.001@hfccf.org');
        Sanctum::actingAs($teacher);

        $this->getJson('/api/preschool/workflows/sync/preview')
            ->assertForbidden();

        $this->postJson('/api/preschool/workflows/sync/run', [])
            ->assertForbidden();

        $this->getJson('/api/preschool/workflows/sync/runs')
            ->assertForbidden();

        $this->getJson("/api/preschool/workflows/sync/runs/{$run->id}")
            ->assertForbidden();

        $this->getJson("/api/preschool/workflows/sync/runs/{$run->id}/items")
            ->assertForbidden();
    }

    public function test_skips_unsupported_source_types_safely(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'sync-admin-006', 'sync.admin.006@hfccf.org');
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/preschool/workflows/sync/preview?source_type=unsupported_source');

        $response->assertOk()
            ->assertJsonPath('data.summary.eligible', 0)
            ->assertJsonCount(0, 'data.items');
    }

    public function test_skips_records_with_missing_workflow_definition(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'sync-admin-007', 'sync.admin.007@hfccf.org');
        Sanctum::actingAs($admin);

        PreschoolWorkflowDefinition::query()->where('key', 'enrollment_admission')->delete();
        $this->createEnrollmentApplication('ENR-SYNC-007', 'submitted');

        $response = $this->getJson('/api/preschool/workflows/sync/preview?definition_key=enrollment_admission&source_type=preschool_enrollment_application&status=submitted&limit=10');

        $response->assertOk()
            ->assertJsonPath('data.summary.eligible', 1)
            ->assertJsonPath('data.summary.skipped', 1)
            ->assertJsonPath('data.items.0.status', 'skipped');

        $this->assertSame(0, PreschoolWorkflowInstance::query()->count());
    }

    public function test_reports_failed_when_workflow_creation_returns_null(): void
    {
        $this->mock(PreschoolWorkflowService::class, function ($mock): void {
            $mock->shouldReceive('findExistingForSource')->andReturnNull();
            $mock->shouldReceive('startForSource')->andReturnNull();
        });

        $admin = $this->makeUserWithRole('adminpreschool', 'sync-admin-008', 'sync.admin.008@hfccf.org');
        Sanctum::actingAs($admin);

        $this->createEnrollmentApplication('ENR-SYNC-008', 'submitted');

        $response = $this->postJson('/api/preschool/workflows/sync/run', [
            'definition_key' => 'enrollment_admission',
            'source_type' => 'preschool_enrollment_application',
            'status' => 'submitted',
            'limit' => 10,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.summary.eligible', 1)
            ->assertJsonPath('data.summary.failed', 1)
            ->assertJsonPath('data.items.0.status', 'failed')
            ->assertJsonPath('data.run.status', 'failed');

        $this->assertSame(0, PreschoolWorkflowInstance::query()->count());
        $this->assertSame(1, PreschoolWorkflowSyncRun::query()->count());
    }

    public function test_run_records_completed_with_errors_and_keeps_successful_items(): void
    {
        $this->mock(PreschoolWorkflowService::class, function ($mock): void {
            $mock->shouldReceive('findExistingForSource')->andReturnNull();
            $mock->shouldReceive('startForSource')->andReturnUsing(function (
                string $definitionKey,
                string $sourceType,
                mixed $sourceId,
                array $options,
                User $actor,
            ): PreschoolWorkflowInstance {
                static $call = 0;
                $call++;

                if ($call === 2) {
                    throw new \RuntimeException('Intentional sync failure.');
                }

                $definition = PreschoolWorkflowDefinition::query()->where('key', $definitionKey)->firstOrFail();

                return PreschoolWorkflowInstance::query()->create([
                    'workflow_definition_id' => $definition->id,
                    'source_type' => $sourceType,
                    'source_id' => (string) $sourceId,
                    'source_label' => $options['source_label'] ?? (string) $sourceId,
                    'status' => 'open',
                    'priority' => 'normal',
                    'started_at' => now(),
                    'metadata' => $options['metadata'] ?? [],
                ]);
            });
        });

        $admin = $this->makeUserWithRole('adminpreschool', 'sync-admin-009', 'sync.admin.009@hfccf.org');
        Sanctum::actingAs($admin);

        $first = $this->createEnrollmentApplication('ENR-SYNC-009', 'submitted');
        $second = $this->createEnrollmentApplication('ENR-SYNC-010', 'submitted');

        $response = $this->postJson('/api/preschool/workflows/sync/run', [
            'definition_key' => 'enrollment_admission',
            'source_type' => 'preschool_enrollment_application',
            'status' => 'submitted',
            'limit' => 2,
            'batch_size' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.summary.eligible', 2)
            ->assertJsonPath('data.summary.created', 1)
            ->assertJsonPath('data.summary.failed', 1)
            ->assertJsonPath('data.run.status', 'completed_with_errors')
            ->assertJsonPath('data.run.batchSize', 1)
            ->assertJsonPath('data.run.processedCount', 2)
            ->assertJsonPath('data.run.startedByUserId', $admin->id);

        $run = PreschoolWorkflowSyncRun::query()->firstOrFail();
        $this->assertSame(2, $run->eligible_count);
        $this->assertSame(2, $run->processed_count);
        $this->assertSame(1, $run->created_count);
        $this->assertSame(1, $run->failed_count);
        $this->assertSame(1, $run->items()->where('result_status', 'created')->count());
        $this->assertSame(1, $run->items()->where('result_status', 'failed')->count());
        $this->assertSame('submitted', $first->fresh()->status);
        $this->assertSame('submitted', $second->fresh()->status);
        $this->assertSame(1, PreschoolWorkflowInstance::query()->count());
    }

    public function test_history_endpoints_return_runs_and_items(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'sync-admin-010', 'sync.admin.010@hfccf.org');
        Sanctum::actingAs($admin);

        $this->createEnrollmentApplication('ENR-SYNC-011', 'submitted');
        $this->postJson('/api/preschool/workflows/sync/run', [
            'definition_key' => 'enrollment_admission',
            'source_type' => 'preschool_enrollment_application',
            'status' => 'submitted',
            'limit' => 10,
            'batch_size' => 1,
        ])->assertOk();

        $history = $this->getJson('/api/preschool/workflows/sync/runs?mode=run&status=completed&definition_key=enrollment_admission&source_type=preschool_enrollment_application');
        $history->assertOk()
            ->assertJsonPath('data.items.0.definitionKey', 'enrollment_admission')
            ->assertJsonPath('data.items.0.mode', 'run')
            ->assertJsonPath('data.pagination.total', 1);

        $runId = $history->json('data.items.0.id');
        $show = $this->getJson("/api/preschool/workflows/sync/runs/{$runId}");
        $show->assertOk()
            ->assertJsonPath('data.id', $runId)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.startedByUserId', $admin->id);

        $items = $this->getJson("/api/preschool/workflows/sync/runs/{$runId}/items?result_status=created");
        $items->assertOk()
            ->assertJsonPath('data.items.0.resultStatus', 'created')
            ->assertJsonPath('data.pagination.total', 1);
    }

    private function createEnrollmentApplication(string $code, string $status): PreschoolEnrollmentApplication
    {
        return PreschoolEnrollmentApplication::query()->create([
            'application_code' => $code,
            'first_name' => 'Sync',
            'last_name' => 'Student',
            'gender' => 'female',
            'date_of_birth' => '2021-01-01',
            'guardian_name' => 'Guardian',
            'guardian_phone' => '+855 12 000 001',
            'guardian_email' => 'guardian@example.com',
            'guardian_address' => 'Phnom Penh',
            'guardian_can_pickup' => true,
            'guardian_is_emergency' => true,
            'status' => $status,
            'application_date' => today()->toDateString(),
            'source' => 'walk_in',
        ]);
    }

    private function createStudent(string $studentCode, string $firstName, string $lastName): PreschoolStudent
    {
        return PreschoolStudent::query()->create([
            'student_code' => $studentCode,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'gender' => 'female',
            'date_of_birth' => '2021-01-01',
            'guardian_name' => 'Guardian',
            'guardian_phone' => '+855 12 000 002',
            'address' => 'Phnom Penh',
            'status' => 'active',
        ]);
    }

    private function makeUserWithRole(string $roleCode, string $id, string $email): User
    {
        $role = Role::query()->with('permissions')->findOrFail($roleCode);

        $user = User::query()->create([
            'id' => $id,
            'first_name' => ucfirst(str_replace('-', ' ', $roleCode)),
            'last_name' => 'User',
            'username' => $id.'-user',
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
