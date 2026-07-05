<?php

namespace Tests\Feature;

use App\Models\PreschoolEnrollmentApplication;
use App\Models\PreschoolWorkflowDefinition;
use App\Models\PreschoolWorkflowInstance;
use App\Models\PreschoolWorkflowSyncRun;
use App\Models\PreschoolWorkflowSyncRunItem;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolWorkflowObservabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-07-04 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_returns_expected_payload_and_metrics(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'obs-admin-001', 'obs.admin.001@hfccf.org');
        Sanctum::actingAs($admin);

        $this->seedObservabilityFixtures($admin);

        $response = $this->getJson('/api/preschool/workflows/observability/dashboard');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'summary' => [
                        'totalRuns',
                        'successfulRuns',
                        'runsWithErrors',
                        'failedRuns',
                        'runningRuns',
                        'staleRuns',
                        'totalProcessed',
                        'totalCreated',
                        'totalExisting',
                        'totalSkipped',
                        'totalFailedItems',
                        'successRate',
                        'failureRate',
                        'averageDurationMs',
                        'longestDurationMs',
                        'averageItemsPerRun',
                    ],
                    'performance' => [
                        'averageDurationMs',
                        'longestDurationMs',
                        'slowestRuns',
                        'durationTrend',
                        'processedItemsTrend',
                        'throughputTrend',
                    ],
                    'health' => [
                        'status',
                        'staleRuns',
                        'recentFailedRuns',
                        'recentRunsWithErrors',
                        'highFailureRateRuns',
                    ],
                    'breakdowns' => [
                        'byDefinition',
                        'bySourceType',
                        'byRunStatus',
                        'byItemStatus',
                        'byActor',
                    ],
                    'trends' => [
                        'runsOverTime',
                        'processedItemsOverTime',
                        'failureRateOverTime',
                        'durationOverTime',
                    ],
                    'recentActivity' => [
                        'recentRuns',
                        'recentFailures',
                        'recentlyCompletedRuns',
                    ],
                    'governance' => [
                        'oldestRunAt',
                        'totalRunRecords',
                        'totalItemRecords',
                        'retentionMode',
                        'automaticPruningEnabled',
                    ],
                    'filters',
                    'generatedAt',
                ],
            ]);

        $response->assertJsonPath('data.summary.totalRuns', 7)
            ->assertJsonPath('data.summary.successfulRuns', 1)
            ->assertJsonPath('data.summary.runsWithErrors', 1)
            ->assertJsonPath('data.summary.failedRuns', 2)
            ->assertJsonPath('data.summary.runningRuns', 2)
            ->assertJsonPath('data.summary.staleRuns', 2)
            ->assertJsonPath('data.summary.totalProcessed', 12)
            ->assertJsonPath('data.summary.totalCreated', 2)
            ->assertJsonPath('data.summary.totalExisting', 2)
            ->assertJsonPath('data.summary.totalSkipped', 1)
            ->assertJsonPath('data.summary.totalFailedItems', 3)
            ->assertJsonPath('data.summary.averageDurationMs', 60000)
            ->assertJsonPath('data.summary.longestDurationMs', 120000)
            ->assertJsonPath('data.summary.averageItemsPerRun', 1.71)
            ->assertJsonPath('data.governance.retentionMode', 'policy_only')
            ->assertJsonPath('data.governance.automaticPruningEnabled', false);

        $this->assertSame('critical', $response->json('data.health.status'));
        $this->assertCount(2, $response->json('data.health.staleRuns'));
        $this->assertContains('validation_error', collect($response->json('data.recentActivity.recentFailures'))->pluck('failureCategory')->all());
        $this->assertContains('database_error', collect($response->json('data.breakdowns.byFailureCategory'))->pluck('failureCategory')->all());
    }

    public function test_dashboard_calculates_duration_throughput_and_handles_missing_timestamps(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'obs-admin-002', 'obs.admin.002@hfccf.org');
        Sanctum::actingAs($admin);

        $this->seedObservabilityFixtures($admin);

        $response = $this->getJson('/api/preschool/workflows/observability/dashboard');

        $slowestRuns = $response->json('data.performance.slowestRuns');
        $runWithZeroDuration = collect($slowestRuns)->firstWhere('id', 1);
        $runWithDuration = collect($slowestRuns)->firstWhere('id', 2);

        $this->assertSame(0, $runWithZeroDuration['durationMs']);
        $this->assertNull($runWithZeroDuration['throughputItemsPerSecond']);
        $this->assertSame(120000, $runWithDuration['durationMs']);
        $this->assertSame(0.0333, $runWithDuration['throughputItemsPerSecond']);
        $this->assertSame(60000, $response->json('data.summary.averageDurationMs'));
    }

    public function test_dashboard_detects_stale_pending_and_running_runs_but_not_fresh_runs(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'obs-admin-003', 'obs.admin.003@hfccf.org');
        Sanctum::actingAs($admin);

        $this->seedObservabilityFixtures($admin);

        $response = $this->getJson('/api/preschool/workflows/observability/dashboard');

        $staleRuns = collect($response->json('data.health.staleRuns'));
        $freshRunningRun = collect($response->json('data.recentActivity.recentRuns'))->firstWhere('id', 7);

        $this->assertTrue((bool) $staleRuns->firstWhere('id', 5)['stale']['isStale']);
        $this->assertSame('Pending longer than the configured threshold.', $staleRuns->firstWhere('id', 5)['stale']['staleReason']);
        $this->assertTrue((bool) $staleRuns->firstWhere('id', 6)['stale']['isStale']);
        $this->assertSame('Running longer than the configured threshold.', $staleRuns->firstWhere('id', 6)['stale']['staleReason']);
        $this->assertFalse((bool) ($freshRunningRun['stale']['isStale'] ?? false));
    }

    public function test_dashboard_filters_results_by_definition_mode_date_status_actor_and_source_type(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'obs-admin-004', 'obs.admin.004@hfccf.org');
        Sanctum::actingAs($admin);

        $this->seedObservabilityFixtures($admin);

        $response = $this->getJson('/api/preschool/workflows/observability/dashboard?definition_key=invoice_collection&source_type=preschool_invoice&status=completed_with_errors&started_by_user_id=obs-admin-004&date_from=2026-07-04&date_to=2026-07-04&mode=run');

        $response->assertOk()
            ->assertJsonPath('data.summary.totalRuns', 1)
            ->assertJsonPath('data.breakdowns.byDefinition.0.definitionKey', 'invoice_collection')
            ->assertJsonPath('data.breakdowns.bySourceType.0.sourceType', 'preschool_invoice')
            ->assertJsonPath('data.breakdowns.byRunStatus.0.status', 'completed_with_errors')
            ->assertJsonPath('data.breakdowns.byActor.0.startedByUserId', 'obs-admin-004');
    }

    public function test_dashboard_classifies_failure_categories_conservatively_and_uses_unknown_for_ambiguous_failures(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'obs-admin-005', 'obs.admin.005@hfccf.org');
        Sanctum::actingAs($admin);

        $this->seedObservabilityFixtures($admin);

        $response = $this->getJson('/api/preschool/workflows/observability/dashboard');
        $failureRows = collect($response->json('data.recentActivity.recentFailures'));

        $this->assertContains('validation_error', $failureRows->pluck('failureCategory')->all());
        $failureCategories = collect($response->json('data.breakdowns.byFailureCategory'))->pluck('failureCategory')->all();

        $this->assertContains('definition_missing', $failureCategories);
        $this->assertContains('permission_error', $failureCategories);
        $this->assertContains('database_error', $failureCategories);
        $this->assertContains('unknown', $failureCategories);
    }

    public function test_teacher_receives_forbidden_and_observability_reads_do_not_mutate_domain_records(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'obs-admin-006', 'obs.admin.006@hfccf.org');
        $teacher = $this->makeUserWithRole('teacher-preschool', 'obs-teacher-001', 'obs.teacher.001@hfccf.org');
        Sanctum::actingAs($teacher);

        $this->seedObservabilityFixtures($admin);

        $workflowCount = PreschoolWorkflowInstance::query()->count();
        $syncRunCount = PreschoolWorkflowSyncRun::query()->count();
        $studentCount = DB::table('preschool_students')->count();
        $notificationCount = DB::table('notifications')->count();

        $this->getJson('/api/preschool/workflows/observability/dashboard')
            ->assertForbidden();

        $this->assertSame($workflowCount, PreschoolWorkflowInstance::query()->count());
        $this->assertSame($syncRunCount, PreschoolWorkflowSyncRun::query()->count());
        $this->assertSame($studentCount, DB::table('preschool_students')->count());
        $this->assertSame($notificationCount, DB::table('notifications')->count());
    }

    private function seedObservabilityFixtures(User $admin): void
    {
        $definitions = [
            'enrollment_admission' => PreschoolWorkflowDefinition::query()->where('key', 'enrollment_admission')->firstOrFail(),
            'invoice_collection' => PreschoolWorkflowDefinition::query()->where('key', 'invoice_collection')->firstOrFail(),
            'health_alert_resolution' => PreschoolWorkflowDefinition::query()->where('key', 'health_alert_resolution')->firstOrFail(),
            'attendance_follow_up' => PreschoolWorkflowDefinition::query()->where('key', 'attendance_follow_up')->firstOrFail(),
        ];

        $this->createRun([
            'id' => 1,
            'mode' => 'run',
            'status' => 'completed',
            'definition_key' => 'enrollment_admission',
            'source_type' => 'preschool_enrollment_application',
            'filters' => ['definition_key' => 'enrollment_admission'],
            'requested_limit' => 10,
            'batch_size' => 25,
            'eligible_count' => 4,
            'processed_count' => 4,
            'created_count' => 1,
            'existing_count' => 1,
            'skipped_count' => 1,
            'failed_count' => 1,
            'started_by_user_id' => $admin->id,
            'started_at' => Carbon::parse('2026-07-04 10:00:00'),
            'completed_at' => Carbon::parse('2026-07-04 10:00:00'),
            'created_at' => Carbon::parse('2026-07-04 10:00:00'),
            'updated_at' => Carbon::parse('2026-07-04 10:00:00'),
        ]);

        $this->createRunItem(1, $definitions['enrollment_admission']->key, 'preschool_enrollment_application', '1001', 'Application 1001', 'created', 'Workflow created successfully.', null, null, Carbon::parse('2026-07-04 10:00:05'));
        $this->createRunItem(1, $definitions['enrollment_admission']->key, 'preschool_enrollment_application', '1002', 'Application 1002', 'existing', 'Workflow already exists.', null, null, Carbon::parse('2026-07-04 10:00:10'));
        $this->createRunItem(1, $definitions['enrollment_admission']->key, 'preschool_enrollment_application', '1003', 'Application 1003', 'skipped', 'Draft records are excluded from this sync.', null, null, Carbon::parse('2026-07-04 10:00:15'));
        $this->createRunItem(1, $definitions['enrollment_admission']->key, 'preschool_enrollment_application', '1004', 'Application 1004', 'failed', 'Validation failed: source id is required.', null, 'Validation failed: source id is required.', Carbon::parse('2026-07-04 10:00:20'));

        $this->createRun([
            'id' => 2,
            'mode' => 'run',
            'status' => 'completed_with_errors',
            'definition_key' => 'invoice_collection',
            'source_type' => 'preschool_invoice',
            'filters' => ['definition_key' => 'invoice_collection'],
            'requested_limit' => 10,
            'batch_size' => 25,
            'eligible_count' => 4,
            'processed_count' => 4,
            'created_count' => 1,
            'existing_count' => 1,
            'skipped_count' => 0,
            'failed_count' => 2,
            'started_by_user_id' => $admin->id,
            'started_at' => Carbon::parse('2026-07-04 09:00:00'),
            'completed_at' => Carbon::parse('2026-07-04 09:02:00'),
            'created_at' => Carbon::parse('2026-07-04 09:00:00'),
            'updated_at' => Carbon::parse('2026-07-04 09:02:00'),
        ]);

        $this->createRunItem(2, $definitions['invoice_collection']->key, 'preschool_invoice', '2001', 'Invoice 2001', 'created', 'Workflow created successfully.', null, null, Carbon::parse('2026-07-04 09:01:00'));
        $this->createRunItem(2, $definitions['invoice_collection']->key, 'preschool_invoice', '2002', 'Invoice 2002', 'existing', 'Workflow already exists.', null, null, Carbon::parse('2026-07-04 09:01:10'));
        $this->createRunItem(2, $definitions['invoice_collection']->key, 'preschool_invoice', '2003', 'Invoice 2003', 'failed', 'Workflow definition was not found.', null, 'Workflow definition was not found.', Carbon::parse('2026-07-04 09:01:20'));
        $this->createRunItem(2, $definitions['invoice_collection']->key, 'preschool_invoice', '2004', 'Invoice 2004', 'failed', 'Workflow creation returned no instance.', null, 'Workflow creation returned no instance.', Carbon::parse('2026-07-04 09:01:30'));

        $this->createRun([
            'id' => 3,
            'mode' => 'run',
            'status' => 'failed',
            'definition_key' => 'health_alert_resolution',
            'source_type' => 'preschool_health_alert',
            'filters' => ['definition_key' => 'health_alert_resolution'],
            'requested_limit' => 10,
            'batch_size' => 25,
            'eligible_count' => 1,
            'processed_count' => 0,
            'created_count' => 0,
            'existing_count' => 0,
            'skipped_count' => 0,
            'failed_count' => 0,
            'started_by_user_id' => $admin->id,
            'started_at' => null,
            'completed_at' => null,
            'failure_message' => 'SQLSTATE[HY000] General error: 2002 Connection refused',
            'created_at' => Carbon::parse('2026-07-03 08:00:00'),
            'updated_at' => Carbon::parse('2026-07-03 08:00:00'),
        ]);

        $this->createRun([
            'id' => 4,
            'mode' => 'run',
            'status' => 'failed',
            'definition_key' => 'attendance_follow_up',
            'source_type' => 'preschool_guardian_communication',
            'filters' => ['definition_key' => 'attendance_follow_up'],
            'requested_limit' => 10,
            'batch_size' => 25,
            'eligible_count' => 1,
            'processed_count' => 0,
            'created_count' => 0,
            'existing_count' => 0,
            'skipped_count' => 0,
            'failed_count' => 0,
            'started_by_user_id' => $admin->id,
            'started_at' => null,
            'completed_at' => null,
            'failure_message' => 'Permission denied.',
            'created_at' => Carbon::parse('2026-07-03 09:00:00'),
            'updated_at' => Carbon::parse('2026-07-03 09:00:00'),
        ]);

        $this->createRun([
            'id' => 5,
            'mode' => 'run',
            'status' => 'pending',
            'definition_key' => 'enrollment_admission',
            'source_type' => 'preschool_enrollment_application',
            'filters' => ['definition_key' => 'enrollment_admission'],
            'requested_limit' => 10,
            'batch_size' => 25,
            'eligible_count' => 1,
            'processed_count' => 0,
            'created_count' => 0,
            'existing_count' => 0,
            'skipped_count' => 0,
            'failed_count' => 0,
            'started_by_user_id' => $admin->id,
            'started_at' => null,
            'completed_at' => null,
            'created_at' => Carbon::parse('2026-07-04 10:00:00'),
            'updated_at' => Carbon::parse('2026-07-04 10:00:00'),
        ]);

        $this->createRun([
            'id' => 6,
            'mode' => 'run',
            'status' => 'running',
            'definition_key' => 'invoice_collection',
            'source_type' => 'preschool_invoice',
            'filters' => ['definition_key' => 'invoice_collection'],
            'requested_limit' => 10,
            'batch_size' => 25,
            'eligible_count' => 3,
            'processed_count' => 3,
            'created_count' => 0,
            'existing_count' => 0,
            'skipped_count' => 0,
            'failed_count' => 0,
            'started_by_user_id' => $admin->id,
            'started_at' => Carbon::parse('2026-07-04 11:20:00'),
            'completed_at' => null,
            'created_at' => Carbon::parse('2026-07-04 11:20:00'),
            'updated_at' => Carbon::parse('2026-07-04 11:20:00'),
        ]);

        $this->createRun([
            'id' => 7,
            'mode' => 'preview',
            'status' => 'running',
            'definition_key' => 'health_alert_resolution',
            'source_type' => 'preschool_health_alert',
            'filters' => ['definition_key' => 'health_alert_resolution'],
            'requested_limit' => 10,
            'batch_size' => 25,
            'eligible_count' => 1,
            'processed_count' => 1,
            'created_count' => 0,
            'existing_count' => 0,
            'skipped_count' => 0,
            'failed_count' => 0,
            'started_by_user_id' => $admin->id,
            'started_at' => Carbon::parse('2026-07-04 11:55:00'),
            'completed_at' => null,
            'created_at' => Carbon::parse('2026-07-04 11:55:00'),
            'updated_at' => Carbon::parse('2026-07-04 11:55:00'),
        ]);
    }

    private function createRun(array $attributes): PreschoolWorkflowSyncRun
    {
        $row = $attributes;
        $row['filters'] = isset($row['filters']) ? json_encode($row['filters']) : null;

        DB::table('preschool_workflow_sync_runs')->insert($row);

        return PreschoolWorkflowSyncRun::query()->findOrFail((int) $attributes['id']);
    }

    private function createRunItem(
        int $runId,
        string $definitionKey,
        string $sourceType,
        string $sourceId,
        string $sourceLabel,
        string $resultStatus,
        ?string $reason,
        ?int $workflowInstanceId,
        ?string $errorMessage,
        Carbon $processedAt,
    ): PreschoolWorkflowSyncRunItem {
        return PreschoolWorkflowSyncRunItem::query()->create([
            'sync_run_id' => $runId,
            'definition_key' => $definitionKey,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'source_label' => $sourceLabel,
            'result_status' => $resultStatus,
            'reason' => $reason,
            'workflow_instance_id' => $workflowInstanceId,
            'error_message' => $errorMessage,
            'processed_at' => $processedAt,
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
