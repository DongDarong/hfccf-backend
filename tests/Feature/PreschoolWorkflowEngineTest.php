<?php

namespace Tests\Feature;

use App\Models\PreschoolClass;
use App\Models\PreschoolEnrollmentApplication;
use App\Models\PreschoolHealthAlert;
use App\Models\PreschoolInvoice;
use App\Models\PreschoolStudent;
use App\Models\PreschoolWorkflowApproval;
use App\Models\PreschoolWorkflowDefinition;
use App\Models\PreschoolWorkflowEvent;
use App\Models\PreschoolWorkflowInstance;
use App\Models\PreschoolWorkflowStep;
use App\Services\PreschoolWorkflowSourceLinkService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolWorkflowEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_definition_seeding_and_listing(): void
    {
        $admin = $this->createUserWithRole('adminpreschool', ['email' => 'workflow-admin@hfccf.org']);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/preschool/workflows/definitions');

        $response->assertOk()
            ->assertJsonPath('data.items.0.key', 'assessment_review');

        $this->assertDatabaseHas('preschool_workflow_definitions', ['key' => 'enrollment_admission']);
        $this->assertDatabaseHas('preschool_workflow_steps', ['key' => 'review']);
    }

    public function test_create_workflow_instance_and_dedupe_source(): void
    {
        $admin = $this->createUserWithRole('adminpreschool', ['email' => 'workflow-admin2@hfccf.org']);
        Sanctum::actingAs($admin);

        $student = $this->createStudent('PS-WF-001', 'Ada', 'Lynn');
        $application = $this->createEnrollmentApplication($student, 'submitted');

        $first = $this->postJson('/api/preschool/workflows', [
            'workflow_definition_key' => 'enrollment_admission',
            'source_type' => 'preschool_enrollment_application',
            'source_id' => (string) $application->id,
            'source_label' => $application->application_code,
            'priority' => 'high',
            'metadata' => ['applicationStatus' => $application->status],
        ]);

        $first->assertCreated()
            ->assertJsonPath('data.workflow.workflowDefinitionKey', 'enrollment_admission')
            ->assertJsonPath('data.workflow.sourceId', (string) $application->id);

        $second = $this->postJson('/api/preschool/workflows', [
            'workflow_definition_key' => 'enrollment_admission',
            'source_type' => 'preschool_enrollment_application',
            'source_id' => (string) $application->id,
            'source_label' => $application->application_code,
            'priority' => 'urgent',
        ]);

        $second->assertCreated()
            ->assertJsonPath('data.workflow.id', $first->json('data.workflow.id'));

        $this->assertSame(1, PreschoolWorkflowInstance::query()->count());
        $this->assertSame(2, PreschoolWorkflowEvent::query()->count());
    }

    public function test_assign_transition_complete_cancel_and_escalate(): void
    {
        $admin = $this->createUserWithRole('adminpreschool', ['email' => 'workflow-admin3@hfccf.org']);
        $teacher = $this->createUserWithRole('teacher-preschool', ['email' => 'workflow-teacher@hfccf.org']);
        Sanctum::actingAs($admin);

        $instance = $this->createWorkflowInstance('health_alert_resolution', 'health_alert', 'alert-001');
        $step = PreschoolWorkflowStep::query()->whereHas('definition', fn ($query) => $query->where('key', 'health_alert_resolution'))->where('key', 'assigned')->firstOrFail();

        $this->patchJson('/api/preschool/workflows/'.$instance->id.'/assign', [
            'assigned_to_user_id' => $teacher->id,
            'assigned_role' => 'teacher-preschool',
        ])->assertOk()
            ->assertJsonPath('data.workflow.assignedToUserId', $teacher->id)
            ->assertJsonPath('data.workflow.status', 'in_progress');

        $this->patchJson('/api/preschool/workflows/'.$instance->id.'/transition', [
            'workflow_step_id' => $step->id,
        ])->assertOk()
            ->assertJsonPath('data.workflow.currentStep.key', 'assigned');

        $this->patchJson('/api/preschool/workflows/'.$instance->id.'/escalate', [
            'reason' => 'Overdue response',
        ])->assertOk()
            ->assertJsonPath('data.workflow.status', 'escalated');

        $this->patchJson('/api/preschool/workflows/'.$instance->id.'/complete')
            ->assertOk()
            ->assertJsonPath('data.workflow.status', 'completed');

        $this->patchJson('/api/preschool/workflows/'.$instance->id.'/cancel')
            ->assertOk()
            ->assertJsonPath('data.workflow.status', 'cancelled');
    }

    public function test_request_and_decide_approval_and_timeline(): void
    {
        $admin = $this->createUserWithRole('adminpreschool', ['email' => 'workflow-admin4@hfccf.org']);
        $teacher = $this->createUserWithRole('teacher-preschool', ['email' => 'workflow-teacher2@hfccf.org']);
        Sanctum::actingAs($admin);

        $instance = $this->createWorkflowInstance('assessment_review', 'assessment_template', 'template-001');

        $approval = $this->postJson('/api/preschool/workflows/'.$instance->id.'/approvals', [
            'requested_to_user_id' => $teacher->id,
            'decision_notes' => 'Please review the template.',
        ]);

        $approval->assertCreated()
            ->assertJsonPath('data.approval.status', 'pending');

        $approvalId = $approval->json('data.approval.id');

        $this->actingWithToken($teacher)
            ->patchJson('/api/preschool/workflows/approvals/'.$approvalId.'/approve', [
                'decision_notes' => 'Approved after review.',
            ])->assertOk()
            ->assertJsonPath('data.approval.status', 'approved');

        $timeline = $this->getJson('/api/preschool/workflows/'.$instance->id.'/timeline');
        $timeline->assertOk()
            ->assertJsonCount(2, 'data.items');

        $this->actingWithToken($admin)
            ->getJson('/api/preschool/workflows/approvals')
            ->assertOk()
            ->assertJsonCount(1, 'data.items');
    }

    public function test_summary_counts_and_permissions(): void
    {
        $admin = $this->createUserWithRole('adminpreschool', ['email' => 'workflow-admin5@hfccf.org']);
        $teacher = $this->createUserWithRole('teacher-preschool', ['email' => 'workflow-teacher3@hfccf.org']);

        $open = $this->createWorkflowInstance('invoice_collection', 'invoice', 'INV-001', ['status' => 'open', 'priority' => 'high']);
        $pending = $this->createWorkflowInstance('attendance_follow_up', 'attendance_alert', 'alert-002', ['status' => 'pending_approval', 'assigned_to_user_id' => $teacher->id, 'assigned_role' => 'teacher-preschool']);
        $completed = $this->createWorkflowInstance('health_alert_resolution', 'health_alert', 'alert-003', ['status' => 'completed']);
        $completed->update(['completed_at' => now()]);

        $summary = $this->actingWithToken($admin)
            ->getJson('/api/preschool/workflows/summary')
            ->assertOk()
            ->json('data');

        $this->assertSame(3, $summary['total']);
        $this->assertSame(2, $summary['pendingWorkflows']);
        $this->assertSame(1, $summary['pendingApproval']);
        $this->assertSame(1, $summary['pendingApprovals']);
        $this->assertSame(1, $summary['completed']);
        $this->assertNotEmpty($summary['byDefinition']);
        $this->assertNotEmpty($summary['byStatus']);
        $this->assertNotEmpty($summary['byPriority']);

        $this->actingWithToken($teacher)
            ->getJson('/api/preschool/workflows')
            ->assertOk();

        $this->actingAs($teacher, 'sanctum')
            ->patchJson('/api/preschool/workflows/'.$open->id.'/complete')
            ->assertForbidden();
    }

    public function test_source_link_resolver_handles_known_and_missing_sources_safely(): void
    {
        $resolver = app(PreschoolWorkflowSourceLinkService::class);

        $application = $this->createEnrollmentApplication($this->createStudent('PS-WF-010', 'Mila', 'Stone'));
        $resolvedSource = $resolver->resolveSource('preschool_enrollment_application', $application->id, null);

        $this->assertSame('preschool_enrollment_application', $resolvedSource['sourceType']);
        $this->assertSame('dashboard-preschool-admin-enrollments', $resolvedSource['sourceRouteName']);
        $this->assertTrue($resolvedSource['sourceExists']);

        $missingSource = $resolver->resolveSource('preschool_attendance_alert', 'missing-alert', null);

        $this->assertSame('preschool_attendance_alert', $missingSource['sourceType']);
        $this->assertFalse($missingSource['sourceExists']);
        $this->assertSame([], $missingSource['sourceRouteParams']);

        $workflow = $this->createWorkflowInstance('health_alert_resolution', 'health_alert', 'alert-010');
        $linkedWorkflow = $resolver->resolveWorkflowLink('health_alert', 'alert-010');

        $this->assertSame($workflow->id, $linkedWorkflow['workflowInstanceId']);
        $this->assertSame('dashboard-preschool-admin-workflow-details', $linkedWorkflow['workflowRoute']);
        $this->assertSame(['id' => $workflow->id], $linkedWorkflow['workflowActionParams']);
    }

    public function test_read_endpoints_do_not_create_workflow_instances(): void
    {
        $admin = $this->createUserWithRole('adminpreschool', ['email' => 'workflow-admin6@hfccf.org']);
        Sanctum::actingAs($admin);

        $this->assertSame(0, PreschoolWorkflowInstance::query()->count());

        $this->getJson('/api/preschool/workflows/summary')->assertOk();
        $this->getJson('/api/preschool/workflows')->assertOk();
        $this->getJson('/api/preschool/workflows/definitions')->assertOk();

        $this->assertSame(0, PreschoolWorkflowInstance::query()->count());
    }

    private function createStudent(string $code, string $firstName, string $lastName): PreschoolStudent
    {
        return PreschoolStudent::query()->create([
            'student_code' => $code,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'gender' => 'female',
            'date_of_birth' => '2020-01-01',
            'guardian_name' => 'Guardian',
            'guardian_phone' => '+855 12 000 000',
            'address' => 'Phnom Penh',
            'status' => 'active',
        ]);
    }

    private function createEnrollmentApplication(PreschoolStudent $student, string $status = 'submitted'): PreschoolEnrollmentApplication
    {
        return PreschoolEnrollmentApplication::query()->create([
            'application_code' => 'ENR-WF-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'first_name' => $student->first_name,
            'last_name' => $student->last_name,
            'gender' => 'female',
            'date_of_birth' => '2020-01-01',
            'guardian_name' => 'Guardian',
            'guardian_phone' => '+855 12 000 001',
            'guardian_can_pickup' => true,
            'guardian_is_emergency' => true,
            'status' => $status,
            'application_date' => now()->toDateString(),
            'source' => 'walk_in',
        ]);
    }

    private function createWorkflowInstance(string $definitionKey, string $sourceType, string $sourceId, array $overrides = []): PreschoolWorkflowInstance
    {
        $definition = PreschoolWorkflowDefinition::query()->where('key', $definitionKey)->firstOrFail();
        $step = $definition->steps()->orderBy('sort_order')->first();

        return PreschoolWorkflowInstance::query()->create(array_merge([
            'workflow_definition_id' => $definition->id,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'source_label' => strtoupper($sourceId),
            'current_step_id' => $step?->id,
            'status' => 'open',
            'priority' => 'normal',
            'metadata' => [],
        ], $overrides));
    }
}
