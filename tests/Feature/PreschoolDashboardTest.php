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

class PreschoolDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_dashboard_summary_includes_canonical_attendance_alert_metrics(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-dashboard-001', 'dashboard001@hfccf.org');
        Sanctum::actingAs($admin);

        app(PreschoolAttendanceConfigurationService::class)->updateSettings($this->attendanceSettingsPayload([
            'absence_alert_days' => 3,
        ]), $admin);

        $class = $this->createClass('PS-DASH-001', 'Dashboard Class');
        $student = $this->createStudent('PS-DASH-001', 'Alice', 'Dashboard');
        $guardian = $this->createGuardian('Guardian Dashboard', '+855 12 666 666', 'guardian.dashboard@hfccf.org');
        $this->linkGuardianToStudent($student->id, $guardian->id, $admin->id);
        $this->attachStudentToClass($class->id, $student->id);

        foreach (['2026-05-11', '2026-05-12', '2026-05-13'] as $date) {
            $this->recordAttendance($class->id, $student->id, $date, 'absent', $admin->id);
        }

        $this->createRepeatedAbsenceCommunication($student->id, $guardian->id, $admin->id, 'queued', now()->toDateTimeString());

        $this->getJson('/api/preschool/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.attendanceAlerts.total', 1)
            ->assertJsonPath('data.attendanceAlerts.open', 1)
            ->assertJsonPath('data.attendanceAlerts.acknowledged', 0)
            ->assertJsonPath('data.attendanceAlerts.overdue', 0)
            ->assertJsonPath('data.attendanceAlerts.byClass.0.classId', $class->id)
            ->assertJsonPath('data.recentAttendanceAlerts.0.studentId', $student->id)
            ->assertJsonPath('data.recentAttendanceAlerts.0.alertType', 'repeated_absence');
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
            'level' => 'Nursery',
            'teacher_user_id' => $teacherId,
            'status' => 'active',
        ]);
    }

    private function createStudent(string $code, string $firstName, string $lastName): PreschoolStudent
    {
        return PreschoolStudent::query()->create([
            'student_code' => $code,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'gender' => 'female',
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

    private function linkGuardianToStudent(string|int $studentId, string|int $guardianId, string|int $createdBy): void
    {
        PreschoolStudentGuardian::query()->create([
            'student_id' => $studentId,
            'guardian_id' => $guardianId,
            'relationship' => 'parent',
            'relationship_type' => 'parent',
            'is_primary' => true,
            'created_by' => $createdBy,
        ]);
    }

    private function attachStudentToClass(string|int $classId, string|int $studentId): void
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

    private function recordAttendance(string|int $classId, string|int $studentId, string $date, string $status, string|int $recordedBy): PreschoolAttendanceRecord
    {
        return PreschoolAttendanceRecord::query()->create([
            'class_id' => $classId,
            'student_id' => $studentId,
            'attendance_date' => $date,
            'status' => $status,
            'recorded_by_user_id' => $recordedBy,
        ]);
    }

    private function createRepeatedAbsenceCommunication(string|int $studentId, string|int $guardianId, string|int $createdBy, string $status, string $createdAt): PreschoolGuardianCommunication
    {
        $communication = PreschoolGuardianCommunication::query()->create([
            'student_id' => $studentId,
            'guardian_id' => $guardianId,
            'source_type' => 'attendance',
            'source_id' => 'absence-streak-'.$studentId,
            'communication_type' => 'repeated_absence',
            'channel' => 'in_app',
            'subject' => 'Repeated absence follow-up',
            'message' => 'Attendance alert created from attendance record.',
            'severity' => 'high',
            'status' => $status,
            'created_by' => $createdBy,
        ]);

        $communication->created_at = $createdAt;
        $communication->updated_at = $createdAt;
        $communication->save();

        return $communication;
    }

    private function attendanceSettingsPayload(array $overrides = []): array
    {
        return array_merge([
            'late_threshold_minutes' => 15,
            'half_day_threshold_minutes' => 180,
            'absence_alert_days' => 3,
            'school_days_per_week' => 5,
            'school_week_label' => 'Monday-Friday',
            'calendar_events_count' => 0,
        ], $overrides);
    }
}
