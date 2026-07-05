<?php

namespace Tests\Feature;

use App\Models\PreschoolWorkflowDefinition;
use App\Models\PreschoolWorkflowEvent;
use App\Models\PreschoolWorkflowInstance;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolHealthAlertApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_run_the_health_alert_lifecycle(): void
    {
        $admin = $this->createUserWithRole('adminpreschool', [
            'first_name' => 'Alert',
            'last_name' => 'Admin',
            'username' => 'alert-admin',
            'email' => 'preschool.alert.admin@hfccf.org',
            'phone' => '+855 12 610 001',
        ]);
        $assignee = $this->createUserWithRole('teacher-preschool', [
            'first_name' => 'Alert',
            'last_name' => 'Teacher',
            'username' => 'alert-teacher',
            'email' => 'preschool.alert.teacher@hfccf.org',
            'phone' => '+855 12 610 002',
        ]);
        Sanctum::actingAs($admin);

        $student = $this->createStudent('STU-ALERT-001', 'Ivy', 'Lim');
        $class = $this->createClass('PS-ALERT-001', 'Alert Class', $assignee->id, trim($assignee->first_name.' '.$assignee->last_name));
        $this->attachStudentToClass($class->id, $student->id);
        DB::table('preschool_student_health_contacts')->insert([
            'student_id' => $student->id,
            'name' => 'Alert Guardian',
            'relationship' => 'Guardian',
            'phone' => '+855 12 610 099',
            'secondary_phone' => null,
            'priority' => 1,
            'is_primary' => true,
            'receive_alerts' => true,
            'status' => 'active',
            'notes' => null,
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $incident = $this->postJson("/api/preschool/students/{$student->id}/health/incidents", [
            'incident_date' => '2026-05-20',
            'incident_type' => 'Severe reaction',
            'severity' => 'critical',
            'action_taken' => 'Admin reviewed the incident immediately.',
            'follow_up_needed' => true,
            'notes' => 'Trigger alert generation.',
            'status' => 'open',
        ])->assertCreated();

        $this->assertDatabaseHas('preschool_health_alerts', [
            'student_id' => $student->id,
            'alert_type' => 'critical_incident',
            'severity' => 'critical',
            'status' => 'new',
        ]);

        $this->assertSame(1, PreschoolWorkflowInstance::query()->count());
        $workflow = PreschoolWorkflowInstance::query()->firstOrFail();
        $this->assertSame('preschool_health_alert', $workflow->source_type);
        $this->assertSame('health_alert_resolution', $workflow->definition?->key);
        $this->assertSame(2, PreschoolWorkflowEvent::query()->count());

        $alerts = $this->getJson("/api/preschool/health/alerts?student_id={$student->id}")
            ->assertOk()
            ->json('data.items');

        $this->assertNotEmpty($alerts);
        $alertId = $alerts[0]['id'];

        $this->postJson("/api/preschool/health/alerts/{$alertId}/acknowledge")
            ->assertOk()
            ->assertJsonPath('data.alert.status', 'acknowledged');

        $assignResponse = $this->postJson("/api/preschool/health/alerts/{$alertId}/assign", [
            'assigned_to_user_id' => $assignee->id,
        ]);
        $assignResponse->assertOk()->assertJsonPath('data.alert.assigned_to_user_id', $assignee->id);

        $this->postJson("/api/preschool/health/alerts/{$alertId}/status", [
            'status' => 'in_progress',
        ])->assertOk()->assertJsonPath('data.alert.status', 'in_progress');

        $this->postJson("/api/preschool/health/alerts/{$alertId}/resolve", [
            'resolution_notes' => 'Reviewed and stabilized.',
        ])->assertOk()->assertJsonPath('data.alert.status', 'resolved');

        $this->postJson("/api/preschool/health/alerts/{$alertId}/close", [
            'resolution_notes' => 'No further action required.',
        ])->assertOk()->assertJsonPath('data.alert.status', 'closed');

        $this->assertDatabaseHas('preschool_student_health_audit_logs', [
            'student_id' => $student->id,
            'entity_type' => 'health_alert',
            'action' => 'alert.closed',
        ]);
    }

    public function test_teacher_can_view_and_acknowledge_assigned_student_alerts_but_cannot_close(): void
    {
        $teacher = $this->createUserWithRole('teacher-preschool', [
            'first_name' => 'Alert',
            'last_name' => 'Teacher',
            'username' => 'alert-teacher-2',
            'email' => 'preschool.alert.teacher2@hfccf.org',
            'phone' => '+855 12 610 003',
        ]);
        Sanctum::actingAs($teacher);

        $student = $this->createStudent('STU-ALERT-002', 'Kai', 'Nim');
        $class = $this->createClass('PS-ALERT-002', 'Teacher Alert Class', $teacher->id, trim($teacher->first_name.' '.$teacher->last_name));
        $this->attachStudentToClass($class->id, $student->id);

        $this->postJson("/api/preschool/students/{$student->id}/health/incidents", [
            'incident_date' => '2026-05-21',
            'incident_type' => 'Mild fever',
            'severity' => 'high',
            'action_taken' => 'Teacher logged for review.',
            'follow_up_needed' => true,
            'notes' => 'Teacher-visible alert.',
            'status' => 'open',
        ])->assertCreated();

        $alert = $this->getJson("/api/preschool/students/{$student->id}/health/alerts")
            ->assertOk()
            ->json('data.items.0');

        $this->assertSame('new', $alert['status']);

        $this->postJson('/api/preschool/health/alerts/'.$alert['id'].'/acknowledge')
            ->assertOk()
            ->assertJsonPath('data.alert.status', 'acknowledged');

        $this->postJson('/api/preschool/health/alerts/'.$alert['id'].'/resolve', [
            'resolution_notes' => 'Teacher should not resolve this.',
        ])->assertForbidden();

        $this->postJson('/api/preschool/health/alerts/'.$alert['id'].'/close', [
            'resolution_notes' => 'Teacher should not close this.',
        ])->assertForbidden();
    }

    public function test_dashboard_summary_returns_alert_counts(): void
    {
        $admin = $this->createUserWithRole('adminpreschool', [
            'first_name' => 'Alert',
            'last_name' => 'Admin',
            'username' => 'alert-admin-2',
            'email' => 'preschool.alert.admin2@hfccf.org',
            'phone' => '+855 12 610 004',
        ]);
        Sanctum::actingAs($admin);

        $student = $this->createStudent('STU-ALERT-003', 'Nia', 'Sa');
        DB::table('preschool_student_health_contacts')->insert([
            'student_id' => $student->id,
            'name' => 'Summary Guardian',
            'relationship' => 'Guardian',
            'phone' => '+855 12 610 199',
            'secondary_phone' => null,
            'priority' => 1,
            'is_primary' => true,
            'receive_alerts' => true,
            'status' => 'active',
            'notes' => null,
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->postJson("/api/preschool/students/{$student->id}/health/incidents", [
            'incident_date' => '2026-05-22',
            'incident_type' => 'Critical incident',
            'severity' => 'critical',
            'action_taken' => 'Alert created for dashboard summary.',
            'follow_up_needed' => true,
            'notes' => 'Summary test.',
            'status' => 'open',
        ])->assertCreated();

        $this->getJson('/api/preschool/health/dashboard-summary')
            ->assertOk()
            ->assertJsonPath('data.summary.newAlerts', 1)
            ->assertJsonPath('data.summary.criticalAlerts', 1);
    }

    private function createStudent(string $publicId, string $firstName, string $lastName): object
    {
        $studentId = DB::table('preschool_students')->insertGetId([
            'public_id' => $publicId,
            'student_code' => 'PS-'.strtoupper(substr($publicId, -4)),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'student_type' => 'paying',
            'gender' => 'female',
            'date_of_birth' => '2020-01-01',
            'guardian_name' => 'Guardian',
            'guardian_phone' => '+855 12 000 000',
            'address' => 'Phnom Penh',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('preschool_students')->where('id', $studentId)->first();
    }

    private function createClass(string $code, string $name, ?string $teacherId = null, ?string $teacherDisplayName = null): object
    {
        $classId = DB::table('preschool_classes')->insertGetId([
            'code' => $code,
            'name' => $name,
            'teacher_user_id' => $teacherId,
            'teacher_display_name' => $teacherDisplayName,
            'level' => 'Nursery',
            'schedule' => 'Mon-Fri 8:00 AM',
            'students_count' => 0,
            'status' => 'active',
            'room' => 'Room A1',
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('preschool_classes')->where('id', $classId)->first();
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
}
