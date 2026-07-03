<?php

namespace Tests\Feature;

use App\Models\PreschoolAutomationTask;
use App\Models\PreschoolClass;
use App\Models\PreschoolGuardian;
use App\Models\PreschoolGuardianCommunication;
use App\Models\PreschoolStudent;
use App\Models\PreschoolStudentGuardian;
use App\Models\PreschoolWorkflowEvent;
use App\Models\PreschoolWorkflowDefinition;
use App\Models\PreschoolWorkflowInstance;
use App\Models\Role;
use App\Models\User;
use App\Services\PreschoolAutomationTaskService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_daily_checks_are_idempotent_and_create_follow_up_work_items(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-auto-001', 'auto001@hfccf.org');
        $teacher = $this->makeUserWithRole('teacher-preschool', 'psc-auto-002', 'auto002@hfccf.org');
        Sanctum::actingAs($admin);

        $class = $this->createClass('PS-AUTO-001', 'Automation Class', $teacher->id);
        $student = $this->createStudent('PS-AUTO-001', 'Lina', 'Student');
        $guardian = $this->createGuardian('Guardian One', '+855 12 111 111', 'guardian.one@hfccf.org');
        $this->linkGuardianToStudent($student->id, $guardian->id, $admin->id);
        $this->attachStudentToClass($class->id, $student->id);
        $this->createAttendanceFollowUpCommunication($student->id, $guardian->id, $admin->id);

        $response = $this->postJson('/api/preschool/automation/run-daily-checks');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.notificationsCreated', 1)
            ->assertJsonPath('data.tasksCreated', 1);

        $this->assertSame(1, PreschoolAutomationTask::query()->count());
        $this->assertSame(1, DB::table('preschool_notifications')->where('notification_type', 'attendance.follow_up')->count());
        $this->assertSame(1, PreschoolWorkflowInstance::query()->count());
        $workflow = PreschoolWorkflowInstance::query()->firstOrFail();
        $this->assertSame('preschool_automation_task', $workflow->source_type);
        $this->assertSame((string) PreschoolAutomationTask::query()->firstOrFail()->id, $workflow->source_id);
        $this->assertSame('attendance_follow_up', $workflow->definition?->key);
        $this->assertSame(2, PreschoolWorkflowEvent::query()->count());

        $this->getJson('/api/preschool/workflows/summary')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.pendingWorkflows', 1)
            ->assertJsonPath('data.open', 1)
            ->assertJsonPath('data.byDefinition.0.workflowDefinitionKey', 'attendance_follow_up');

        $secondRun = $this->postJson('/api/preschool/automation/run-daily-checks');

        $secondRun
            ->assertOk()
            ->assertJsonPath('data.notificationsCreated', 0)
            ->assertJsonPath('data.tasksCreated', 0);

        $this->assertSame(1, PreschoolAutomationTask::query()->count());
        $this->assertSame(1, DB::table('preschool_notifications')->where('notification_type', 'attendance.follow_up')->count());
        $this->assertSame(1, PreschoolWorkflowInstance::query()->count());
        $this->assertSame(2, PreschoolWorkflowEvent::query()->count());

        $teacherView = $this->actingAs($teacher, 'sanctum')->getJson('/api/preschool/automation-tasks');
        $teacherView
            ->assertOk()
            ->assertJsonPath('data.summary.total', 1)
            ->assertJsonPath('data.summary.open', 1)
            ->assertJsonPath('data.items.0.assignedToUserId', $teacher->id);
    }

    public function test_automation_task_actions_support_list_scoping_complete_cancel_assign_and_permissions(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-auto-010', 'auto010@hfccf.org');
        $teacher = $this->makeUserWithRole('teacher-preschool', 'psc-auto-011', 'auto011@hfccf.org');
        $otherTeacher = $this->makeUserWithRole('teacher-preschool', 'psc-auto-012', 'auto012@hfccf.org');
        Sanctum::actingAs($admin);

        $class = $this->createClass('PS-AUTO-010', 'Action Class', $teacher->id);
        $student = $this->createStudent('PS-AUTO-010', 'Mina', 'Student');
        $this->attachStudentToClass($class->id, $student->id);
        $definition = PreschoolWorkflowDefinition::query()->where('key', 'attendance_follow_up')->firstOrFail();
        $step = $definition->steps()->orderBy('sort_order')->first();
        $workflowInstance = PreschoolWorkflowInstance::query()->create([
            'workflow_definition_id' => $definition->id,
            'source_type' => 'invoice',
            'source_id' => 'task-2',
            'source_label' => 'Review overdue invoice',
            'current_step_id' => $step?->id,
            'status' => 'open',
            'priority' => 'normal',
            'metadata' => [],
        ]);

        $assignedTask = PreschoolAutomationTask::query()->create([
            'task_type' => 'attendance.follow_up',
            'title' => 'Follow up with guardian',
            'description' => 'Call the guardian about attendance.',
            'priority' => 'high',
            'status' => PreschoolAutomationTask::STATUS_OPEN,
            'assigned_to_user_id' => $teacher->id,
            'assigned_role' => 'adminpreschool',
            'due_at' => now()->addDay(),
            'source_type' => 'attendance_communication',
            'source_id' => 'task-1',
            'preschool_student_id' => $student->id,
            'preschool_class_id' => $class->id,
            'action_route' => 'dashboard-preschool-admin-notifications',
            'action_params' => ['studentId' => $student->id],
            'created_by' => $admin->id,
        ]);

        $overdueTask = PreschoolAutomationTask::query()->create([
            'task_type' => 'payments.follow_up',
            'title' => 'Review overdue invoice',
            'description' => 'Check the invoice status.',
            'priority' => 'urgent',
            'status' => PreschoolAutomationTask::STATUS_OVERDUE,
            'assigned_to_user_id' => $teacher->id,
            'assigned_role' => 'adminpreschool',
            'due_at' => now()->subDay(),
            'source_type' => 'invoice',
            'source_id' => 'task-2',
            'preschool_student_id' => $student->id,
            'preschool_class_id' => $class->id,
            'action_route' => 'dashboard-preschool-admin-notifications',
            'action_params' => [],
            'created_by' => $admin->id,
        ]);

        $this->actingAs($teacher, 'sanctum')
            ->getJson('/api/preschool/automation-tasks?status=all')
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.workflowInstanceId', $workflowInstance->id)
            ->assertJsonPath('data.items.0.workflowRoute', 'dashboard-preschool-admin-workflow-details');

        $this->actingAs($otherTeacher, 'sanctum')
            ->getJson('/api/preschool/automation-tasks')
            ->assertOk()
            ->assertJsonCount(0, 'data.items');

        $this->actingAs($teacher, 'sanctum')
            ->patchJson('/api/preschool/automation-tasks/'.$assignedTask->id.'/complete')
            ->assertOk()
            ->assertJsonPath('data.task.status', 'completed');

        $this->actingAs($teacher, 'sanctum')
            ->patchJson('/api/preschool/automation-tasks/'.$overdueTask->id.'/cancel')
            ->assertOk()
            ->assertJsonPath('data.task.status', 'cancelled');

        $reassigned = $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/preschool/automation-tasks/'.$assignedTask->id.'/assign', [
                'assigned_to_user_id' => $otherTeacher->id,
                'assigned_role' => 'teacher-preschool',
            ]);

        $reassigned
            ->assertStatus(422);

        $assignableTask = PreschoolAutomationTask::query()->create([
            'task_type' => 'health.follow_up',
            'title' => 'Review health alert',
            'description' => 'Follow up with the class team.',
            'priority' => 'high',
            'status' => PreschoolAutomationTask::STATUS_OVERDUE,
            'assigned_to_user_id' => $teacher->id,
            'assigned_role' => 'adminpreschool',
            'due_at' => now()->subDay(),
            'source_type' => 'health_alert',
            'source_id' => 'task-3',
            'preschool_student_id' => $student->id,
            'preschool_class_id' => $class->id,
            'action_route' => 'dashboard-preschool-admin-notifications',
            'action_params' => [],
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/preschool/automation-tasks/'.$assignableTask->id.'/assign', [
                'assigned_to_user_id' => $otherTeacher->id,
                'assigned_role' => 'teacher-preschool',
            ])
            ->assertOk()
            ->assertJsonPath('data.task.assigned_to_user_id', $otherTeacher->id)
            ->assertJsonPath('data.task.status', 'in_progress');

        $this->actingAs($otherTeacher, 'sanctum')
            ->patchJson('/api/preschool/automation-tasks/'.$assignableTask->id.'/complete')
            ->assertOk()
            ->assertJsonPath('data.task.status', 'completed');
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

    private function createClass(string $code, string $name, ?string $teacherId = null): PreschoolClass
    {
        return PreschoolClass::query()->create([
            'code' => $code,
            'name' => $name,
            'teacher_user_id' => $teacherId,
            'teacher_display_name' => $teacherId ? 'Assigned Teacher' : null,
            'level' => 'Nursery',
            'schedule' => 'Mon-Fri 8:00 AM',
            'students_count' => 0,
            'status' => 'active',
            'room' => 'Room A1',
            'notes' => null,
        ]);
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

    private function createGuardian(string $name, string $phone, string $email): PreschoolGuardian
    {
        return PreschoolGuardian::query()->create([
            'full_name' => $name,
            'phone' => $phone,
            'email' => $email,
            'status' => 'active',
        ]);
    }

    private function linkGuardianToStudent(int $studentId, int $guardianId, string|int $userId): PreschoolStudentGuardian
    {
        return PreschoolStudentGuardian::query()->create([
            'student_id' => $studentId,
            'guardian_id' => $guardianId,
            'relationship_type' => 'guardian',
            'is_primary' => true,
            'can_pickup' => true,
            'emergency_priority' => 1,
            'status' => 'active',
            'starts_at' => now()->toDateString(),
            'notes' => null,
            'created_by_user_id' => $userId,
            'updated_by_user_id' => $userId,
        ]);
    }

    private function attachStudentToClass(int $classId, int $studentId): void
    {
        DB::table('preschool_class_students')->insert([
            'class_id' => $classId,
            'student_id' => $studentId,
            'enrolled_at' => now(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createAttendanceFollowUpCommunication(int $studentId, int $guardianId, string|int $createdBy, ?string $createdAt = null): PreschoolGuardianCommunication
    {
        $communication = PreschoolGuardianCommunication::query()->create([
            'student_id' => $studentId,
            'guardian_id' => $guardianId,
            'source_type' => 'attendance',
            'source_id' => 'absence-streak-'.$studentId,
            'communication_type' => 'repeated_absence',
            'channel' => 'in_app',
            'subject' => 'Repeated absence follow-up',
            'message' => 'Attendance alert created from a repeated absence streak.',
            'severity' => 'high',
            'status' => 'queued',
            'created_by' => $createdBy,
        ]);

        if ($createdAt !== null) {
            $communication->created_at = $createdAt;
            $communication->save();
        }

        return $communication;
    }
}
