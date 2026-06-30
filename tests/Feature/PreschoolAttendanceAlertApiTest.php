<?php

namespace Tests\Feature;

use App\Models\PreschoolAttendanceRecord;
use App\Models\PreschoolClass;
use App\Models\PreschoolGuardian;
use App\Models\PreschoolGuardianCommunication;
use App\Models\PreschoolStudent;
use App\Models\PreschoolStudentGuardian;
use App\Models\Role;
use App\Models\User;
use App\Support\PreschoolAttendanceConfigurationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolAttendanceAlertApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_get_attendance_alerts_returns_repeated_absence_communications_with_summary(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-alert-001', 'alerts001@hfccf.org');
        Sanctum::actingAs($admin);

        app(PreschoolAttendanceConfigurationService::class)->updateSettings($this->attendanceSettingsPayload([
            'absence_alert_days' => 3,
        ]), $admin);

        $class = $this->createClass('PS-ALERT-001', 'Alert Class');
        $student = $this->createStudent('PS-ALERT-001', 'Alice', 'Student');
        $guardian = $this->createGuardian('Guardian One', '+855 12 111 111', 'guardian.one@hfccf.org');
        $this->linkGuardianToStudent($student->id, $guardian->id, $admin->id);
        $this->attachStudentToClass($class->id, $student->id);

        foreach (['2026-05-11', '2026-05-12', '2026-05-13'] as $date) {
            $this->recordAttendance($class->id, $student->id, $date, 'absent', $admin->id);
        }

        $this->createRepeatedAbsenceCommunication($student->id, $guardian->id, $admin->id, 'queued', '2026-05-13 08:00:00');

        $response = $this->getJson('/api/preschool/attendance-alerts');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.total', 1)
            ->assertJsonPath('data.summary.open', 1)
            ->assertJsonPath('data.summary.acknowledged', 0)
            ->assertJsonPath('data.items.0.alertType', 'repeated_absence')
            ->assertJsonPath('data.items.0.studentId', $student->id)
            ->assertJsonPath('data.items.0.guardianId', $guardian->id)
            ->assertJsonPath('data.items.0.classId', $class->id)
            ->assertJsonPath('data.items.0.sourceType', 'attendance');
    }

    public function test_get_attendance_alerts_filters_by_student_class_status_and_date_and_excludes_non_attendance_alerts(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-alert-010', 'alerts010@hfccf.org');
        Sanctum::actingAs($admin);

        app(PreschoolAttendanceConfigurationService::class)->updateSettings($this->attendanceSettingsPayload([
            'absence_alert_days' => 2,
        ]), $admin);

        $classOne = $this->createClass('PS-ALERT-010A', 'Alert Class A');
        $classTwo = $this->createClass('PS-ALERT-010B', 'Alert Class B');
        $studentOne = $this->createStudent('PS-ALERT-010A', 'Alice', 'Alert');
        $studentTwo = $this->createStudent('PS-ALERT-010B', 'Bopha', 'Alert');
        $guardianOne = $this->createGuardian('Guardian A', '+855 12 222 222', 'guardian.a@hfccf.org');
        $guardianTwo = $this->createGuardian('Guardian B', '+855 12 333 333', 'guardian.b@hfccf.org');
        $this->linkGuardianToStudent($studentOne->id, $guardianOne->id, $admin->id);
        $this->linkGuardianToStudent($studentTwo->id, $guardianTwo->id, $admin->id);
        $this->attachStudentToClass($classOne->id, $studentOne->id);
        $this->attachStudentToClass($classTwo->id, $studentTwo->id);

        foreach (['2026-05-11', '2026-05-12'] as $date) {
            $this->recordAttendance($classOne->id, $studentOne->id, $date, 'absent', $admin->id);
        }

        foreach (['2026-05-11', '2026-05-12'] as $date) {
            $this->recordAttendance($classTwo->id, $studentTwo->id, $date, 'absent', $admin->id);
        }

        $this->createRepeatedAbsenceCommunication($studentOne->id, $guardianOne->id, $admin->id, 'queued', '2026-05-12 08:00:00');
        $this->createRepeatedAbsenceCommunication($studentTwo->id, $guardianTwo->id, $admin->id, 'queued', '2026-05-12 08:00:00');

        PreschoolGuardianCommunication::query()->create([
            'student_id' => $studentOne->id,
            'guardian_id' => $guardianOne->id,
            'source_type' => 'attendance',
            'source_id' => 'late-streak-'.$studentOne->id,
            'communication_type' => 'late_pattern',
            'channel' => 'in_app',
            'subject' => 'Late pattern',
            'message' => 'Should only appear when explicitly requested.',
            'severity' => 'medium',
            'status' => 'queued',
            'created_by' => $admin->id,
        ]);

        $this->getJson('/api/preschool/attendance-alerts?student_id='.$studentOne->id.'&class_id='.$classOne->id.'&status=queued&date_from=2026-05-10&date_to=2026-05-13')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.studentId', $studentOne->id);

        $this->getJson('/api/preschool/attendance-alerts?communication_type=late_pattern')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.alertType', 'late_pattern');

        $this->getJson('/api/preschool/attendance-alerts')
            ->assertOk()
            ->assertJsonCount(2, 'data.items');
    }

    public function test_get_attendance_alerts_supports_teacher_scope(): void
    {
        $teacher = $this->makeUserWithRole('teacher-preschool', 'psc-alert-020', 'alerts020@hfccf.org');
        Sanctum::actingAs($teacher);

        $class = $this->createClass('PS-ALERT-020', 'Teacher Class', $teacher->id);
        $student = $this->createStudent('PS-ALERT-020', 'Teacher', 'Student');
        $guardian = $this->createGuardian('Guardian Teacher', '+855 12 444 444', 'guardian.teacher@hfccf.org');
        $this->linkGuardianToStudent($student->id, $guardian->id, $teacher->id);
        $this->attachStudentToClass($class->id, $student->id);

        foreach (['2026-05-11', '2026-05-12', '2026-05-13'] as $date) {
            $this->recordAttendance($class->id, $student->id, $date, 'absent', $teacher->id);
        }

        $this->createRepeatedAbsenceCommunication($student->id, $guardian->id, $teacher->id, 'queued', '2026-05-13 08:00:00');

        $this->getJson('/api/preschool/attendance-alerts?class_id='.$class->id)
            ->assertOk()
            ->assertJsonCount(1, 'data.items');
    }

    public function test_get_attendance_alerts_summary_counts_acknowledged_and_overdue_records(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-alert-030', 'alerts030@hfccf.org');
        Sanctum::actingAs($admin);

        $class = $this->createClass('PS-ALERT-030', 'Summary Class');
        $student = $this->createStudent('PS-ALERT-030', 'Summary', 'Student');
        $guardian = $this->createGuardian('Guardian Summary', '+855 12 555 555', 'guardian.summary@hfccf.org');
        $this->linkGuardianToStudent($student->id, $guardian->id, $admin->id);
        $this->attachStudentToClass($class->id, $student->id);

        foreach (['2026-05-11', '2026-05-12', '2026-05-13'] as $date) {
            $this->recordAttendance($class->id, $student->id, $date, 'absent', $admin->id);
        }

        $queued = $this->createRepeatedAbsenceCommunication($student->id, $guardian->id, $admin->id, 'sent', now()->subDays(2)->toDateTimeString());

        $acknowledged = PreschoolGuardianCommunication::query()->create([
            'student_id' => $student->id,
            'guardian_id' => $guardian->id,
            'source_type' => 'attendance',
            'source_id' => 'absence-streak-'.$student->id.'-follow-up',
            'communication_type' => 'repeated_absence',
            'channel' => 'in_app',
            'subject' => 'Repeated absence follow-up',
            'message' => 'Follow-up already acknowledged.',
            'severity' => 'high',
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
            'created_by' => $admin->id,
        ]);
        $acknowledged->created_at = now()->subMinutes(30);
        $acknowledged->save();

        $response = $this->getJson('/api/preschool/attendance-alerts');

        $response
            ->assertOk()
            ->assertJsonPath('data.summary.total', 2)
            ->assertJsonPath('data.summary.open', 1)
            ->assertJsonPath('data.summary.acknowledged', 1)
            ->assertJsonPath('data.summary.overdue', 1);
    }

    private function makeUserWithRole(string $roleCode, string $id, string $email): User
    {
        $role = Role::query()->with('permissions')->findOrFail($roleCode);

        $user = User::query()->create([
            'id' => $id,
            'first_name' => ucfirst(str_replace('-', ' ', $roleCode)),
            'last_name' => 'User',
            'username' => ucfirst(str_replace('-', ' ', $roleCode)).' User',
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

    private function attendanceSettingsPayload(array $overrides = []): array
    {
        return array_merge([
            'late_threshold_minutes' => 15,
            'half_day_threshold_minutes' => 180,
            'absence_alert_days' => 3,
            'guardian_alert_enabled' => true,
            'teacher_alert_enabled' => true,
            'admin_alert_enabled' => true,
            'monday_enabled' => true,
            'tuesday_enabled' => true,
            'wednesday_enabled' => true,
            'thursday_enabled' => true,
            'friday_enabled' => true,
            'saturday_enabled' => false,
            'sunday_enabled' => false,
        ], $overrides);
    }

    private function recordAttendance(int $classId, int $studentId, string $date, string $status, string|int $recordedBy): PreschoolAttendanceRecord
    {
        return PreschoolAttendanceRecord::query()->create([
            'class_id' => $classId,
            'student_id' => $studentId,
            'recorded_by_user_id' => $recordedBy,
            'attendance_date' => $date,
            'status' => $status,
            'note' => null,
        ]);
    }

    private function createRepeatedAbsenceCommunication(int $studentId, int $guardianId, string|int $createdBy, string $status = 'queued', ?string $createdAt = null): PreschoolGuardianCommunication
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
            'status' => $status,
            'created_by' => $createdBy,
        ]);

        if ($createdAt !== null) {
            $communication->created_at = $createdAt;
            $communication->save();
        }

        return $communication;
    }
}
