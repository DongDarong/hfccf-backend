<?php

namespace Tests\Feature;

use App\Models\PreschoolAttendanceRecord;
use App\Models\PreschoolClass;
use App\Models\PreschoolGuardian;
use App\Models\PreschoolGuardianCommunication;
use App\Models\PreschoolHealthAlert;
use App\Models\PreschoolStudent;
use App\Models\PreschoolStudentAssessment;
use App\Models\PreschoolStudentGuardian;
use App\Models\Role;
use App\Models\User;
use App\Services\PreschoolGuardianCommunicationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolGuardianCommunicationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_create_manual_communication_and_view_student_timeline(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-comm-001', 'comm001@hfccf.org');
        Sanctum::actingAs($admin);

        $student = $this->createStudent('PS-COMM-001', 'Lina', 'Chan');
        $guardian = $this->createGuardian('Guardian One', '+855 12 111 111', 'guardian.one@hfccf.org');
        $this->linkRelationship($student, $guardian, true, 1, $admin->id);

        $create = $this->postJson("/api/preschool/students/{$student->id}/guardian-communications", [
            'guardian_id' => $guardian->id,
            'subject' => 'Follow-up requested',
            'message' => 'Please review the health alert and reply when available.',
            'severity' => 'high',
            'channel' => 'manual_note',
        ]);

        $create
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.communication.subject', 'Follow-up requested')
            ->assertJsonPath('data.communication.guardianId', $guardian->id);

        $this->getJson("/api/preschool/students/{$student->id}/guardian-communications")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.summary.total', 1);
    }

    public function test_admin_can_mark_sent_acknowledged_and_cancel_communications(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-comm-010', 'comm010@hfccf.org');
        Sanctum::actingAs($admin);

        $student = $this->createStudent('PS-COMM-010', 'Mina', 'Sok');
        $guardian = $this->createGuardian('Guardian Two', '+855 12 222 222', 'guardian.two@hfccf.org');
        $this->linkRelationship($student, $guardian, true, 1, $admin->id);

        $communication = $this->postJson("/api/preschool/students/{$student->id}/guardian-communications", [
            'guardian_id' => $guardian->id,
            'subject' => 'Contact guardian',
            'message' => 'Initial note for status transition coverage.',
            'severity' => 'medium',
            'channel' => 'phone',
        ])->json('data.communication');

        $this->postJson("/api/preschool/guardian-communications/{$communication['id']}/sent")
            ->assertOk()
            ->assertJsonPath('data.communication.status', 'sent');
        $this->assertNotNull(PreschoolGuardianCommunication::query()->findOrFail($communication['id'])->sent_at);

        $this->postJson("/api/preschool/guardian-communications/{$communication['id']}/acknowledge")
            ->assertOk()
            ->assertJsonPath('data.communication.status', 'acknowledged');
        $this->assertNotNull(PreschoolGuardianCommunication::query()->findOrFail($communication['id'])->acknowledged_at);

        $cancelled = $this->postJson("/api/preschool/guardian-communications/{$communication['id']}/cancel")
            ->assertOk()
            ->assertJsonPath('data.communication.status', 'cancelled')
            ->json('data.communication');

        $this->assertSame('cancelled', $cancelled['status']);
    }

    public function test_teacher_can_create_and_view_assigned_student_communications_but_not_unrelated_students(): void
    {
        $teacher = $this->makeUserWithRole('teacher-preschool', 'psc-comm-020', 'comm020@hfccf.org');
        Sanctum::actingAs($teacher);

        $class = $this->createClass('PS-COMM-020', 'Teacher Class', $teacher->id);
        $student = $this->createStudent('PS-COMM-020A', 'Rina', 'Ly');
        $otherStudent = $this->createStudent('PS-COMM-020B', 'Other', 'Student');
        $this->attachStudentToClass($class->id, $student->id);

        $created = $this->postJson("/api/preschool/students/{$student->id}/guardian-communications", [
            'subject' => 'Teacher follow-up',
            'message' => 'Assigned teacher follow-up note.',
            'severity' => 'low',
            'channel' => 'manual_note',
        ]);

        $created
            ->assertCreated()
            ->assertJsonPath('data.communication.subject', 'Teacher follow-up');

        $this->postJson("/api/preschool/students/{$otherStudent->id}/guardian-communications", [
            'subject' => 'Blocked follow-up',
            'message' => 'Should not be allowed.',
            'severity' => 'low',
            'channel' => 'manual_note',
        ])->assertForbidden();

        $this->getJson("/api/preschool/students/{$student->id}/guardian-communications")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson("/api/preschool/students/{$otherStudent->id}/guardian-communications")
            ->assertForbidden();
    }

    public function test_admin_can_view_guardian_timeline(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-comm-030', 'comm030@hfccf.org');
        Sanctum::actingAs($admin);

        $student = $this->createStudent('PS-COMM-030', 'Sok', 'Dara');
        $guardian = $this->createGuardian('Guardian Three', '+855 12 333 333', 'guardian.three@hfccf.org');
        $this->linkRelationship($student, $guardian, true, 1, $admin->id);

        $communication = $this->postJson("/api/preschool/students/{$student->id}/guardian-communications", [
            'guardian_id' => $guardian->id,
            'subject' => 'Guardian timeline item',
            'message' => 'A guardian-linked record for timeline testing.',
            'severity' => 'medium',
            'channel' => 'email',
        ])->json('data.communication');

        $this->getJson("/api/preschool/guardians/{$guardian->id}/communications")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $communication['id']);
    }

    public function test_duplicate_prevention_reuses_same_health_alert_source_event(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-comm-040', 'comm040@hfccf.org');
        Sanctum::actingAs($admin);

        $student = $this->createStudent('PS-COMM-040', 'Nita', 'Mey');
        $guardian = $this->createGuardian('Guardian Four', '+855 12 444 444', 'guardian.four@hfccf.org');
        $this->linkRelationship($student, $guardian, true, 1, $admin->id);

        $alert = PreschoolHealthAlert::query()->create([
            'student_id' => $student->id,
            'alert_type' => 'severe_allergy',
            'severity' => 'critical',
            'status' => 'new',
            'title' => 'Severe allergy',
            'description' => 'Peanut allergy requires guardian follow-up.',
            'source_type' => 'health',
            'source_id' => 'health-alert-040',
        ]);

        $service = app(PreschoolGuardianCommunicationService::class);
        $service->syncHealthAlert($alert, $admin);
        $service->syncHealthAlert($alert, $admin);

        $this->assertSame(1, PreschoolGuardianCommunication::query()
            ->where('source_type', 'health_alert')
            ->where('source_id', (string) $alert->id)
            ->count());
    }

    public function test_coach_cannot_access_guardian_communication_index(): void
    {
        $coach = $this->makeUserWithRole('coach', 'psc-comm-050', 'comm050@hfccf.org');
        Sanctum::actingAs($coach);

        $this->getJson('/api/preschool/guardian-communications')->assertForbidden();
    }

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

    private function createGuardian(string $name, string $phone, string $email): PreschoolGuardian
    {
        return PreschoolGuardian::query()->create([
            'full_name' => $name,
            'phone' => $phone,
            'email' => $email,
            'status' => 'active',
        ]);
    }

    private function linkRelationship(PreschoolStudent $student, PreschoolGuardian $guardian, bool $primary, int $priority, string|int|null $userId): PreschoolStudentGuardian
    {
        return PreschoolStudentGuardian::query()->create([
            'student_id' => $student->id,
            'guardian_id' => $guardian->id,
            'relationship_type' => 'guardian',
            'is_primary' => $primary,
            'can_pickup' => true,
            'emergency_priority' => $priority,
            'status' => 'active',
            'starts_at' => now()->toDateString(),
            'created_by_user_id' => $userId,
            'updated_by_user_id' => $userId,
        ]);
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
