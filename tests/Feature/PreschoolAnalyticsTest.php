<?php

namespace Tests\Feature;

use App\Models\PreschoolAttendanceRecord;
use App\Models\PreschoolAttendanceSession;
use App\Models\PreschoolClass;
use App\Models\PreschoolGuardian;
use App\Models\PreschoolGuardianCommunication;
use App\Models\PreschoolScheduleEntry;
use App\Models\PreschoolStudent;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_dashboard_analytics_returns_summary_and_trends(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'ana-admin-001', 'ana-admin-001@hfccf.org');
        Sanctum::actingAs($admin);

        $this->seedAnalyticsDataset($admin);

        $this->getJson('/api/preschool/analytics/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.scope', 'dashboard')
            ->assertJsonPath('data.summary.activeStudents', 2)
            ->assertJsonPath('data.summary.sessionsGenerated', 2)
            ->assertJsonPath('data.summary.totalAlerts', 2)
            ->assertJsonPath('data.trends.attendance.today.total', 2);
    }

    public function test_attendance_analytics_returns_breakdowns_and_filters(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'ana-admin-010', 'ana-admin-010@hfccf.org');
        Sanctum::actingAs($admin);

        $dataset = $this->seedAnalyticsDataset($admin);

        $this->getJson('/api/preschool/analytics/attendance?class_id='.$dataset['classOne']->id)
            ->assertOk()
            ->assertJsonPath('data.scope', 'attendance')
            ->assertJsonPath('data.summary.total', 2)
            ->assertJsonPath('data.summary.present', 1)
            ->assertJsonPath('data.breakdowns.byClass.0.classId', $dataset['classOne']->id)
            ->assertJsonPath('data.breakdowns.byTeacher.0.teacherUserId', $admin->id);
    }

    public function test_session_analytics_reports_missing_and_rates(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'ana-admin-020', 'ana-admin-020@hfccf.org');
        Sanctum::actingAs($admin);

        $this->seedAnalyticsDataset($admin);

        $this->getJson('/api/preschool/analytics/sessions')
            ->assertOk()
            ->assertJsonPath('data.scope', 'sessions')
            ->assertJsonPath('data.summary.totalSessions', 2)
            ->assertJsonPath('data.summary.completed', 1)
            ->assertJsonPath('data.summary.missing', 1)
            ->assertJsonPath('data.summary.averageSessionDuration', 45);
    }

    public function test_schedule_analytics_reports_heatmap_and_utilization(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'ana-admin-030', 'ana-admin-030@hfccf.org');
        Sanctum::actingAs($admin);

        $dataset = $this->seedAnalyticsDataset($admin);

        $this->getJson('/api/preschool/analytics/schedules?class_id='.$dataset['classOne']->id)
            ->assertOk()
            ->assertJsonPath('data.scope', 'schedules')
            ->assertJsonPath('data.filters.classId', $dataset['classOne']->id)
            ->assertJsonStructure(['data' => ['summary' => ['activeSchedules', 'generatedSessions'], 'breakdowns' => ['byClass']]]);
    }

    public function test_alert_analytics_uses_canonical_alert_records(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'ana-admin-040', 'ana-admin-040@hfccf.org');
        Sanctum::actingAs($admin);

        $this->seedAnalyticsDataset($admin);

        $this->getJson('/api/preschool/analytics/alerts')
            ->assertOk()
            ->assertJsonPath('data.scope', 'alerts')
            ->assertJsonPath('data.summary.totalAlerts', 2)
            ->assertJsonPath('data.summary.open', 1)
            ->assertJsonPath('data.summary.acknowledged', 1)
            ->assertJsonPath('data.breakdowns.byAlertType.0.communication_type', 'repeated_absence');
    }

    public function test_student_analytics_reports_guardian_and_health_counts(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'ana-admin-050', 'ana-admin-050@hfccf.org');
        Sanctum::actingAs($admin);

        $this->seedAnalyticsDataset($admin);

        $this->getJson('/api/preschool/analytics/students')
            ->assertOk()
            ->assertJsonPath('data.scope', 'students')
            ->assertJsonPath('data.summary.activeStudents', 2)
            ->assertJsonPath('data.summary.guardianContacts', 2)
            ->assertJsonPath('data.breakdowns.byClass.0.students', 2);
    }

    public function test_teacher_analytics_scopes_to_assigned_classes(): void
    {
        $teacher = $this->makeUserWithRole('teacher-preschool', 'ana-teacher-001', 'ana-teacher-001@hfccf.org');
        $otherTeacher = $this->makeUserWithRole('teacher-preschool', 'ana-teacher-002', 'ana-teacher-002@hfccf.org');
        Sanctum::actingAs($teacher);

        $this->seedAnalyticsDataset($teacher, $otherTeacher);

        $this->getJson('/api/preschool/analytics/teachers')
            ->assertOk()
            ->assertJsonPath('data.scope', 'teachers')
            ->assertJsonPath('data.summary.assignedClasses', 1)
            ->assertJsonPath('data.summary.students', 1);

        $this->getJson('/api/preschool/analytics/teachers?teacher_user_id='.$otherTeacher->id)
            ->assertForbidden();
    }

    public function test_guardian_contact_analytics_reports_breakdowns(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'ana-admin-060', 'ana-admin-060@hfccf.org');
        Sanctum::actingAs($admin);

        $this->seedAnalyticsDataset($admin);

        $this->getJson('/api/preschool/analytics/guardian-contacts')
            ->assertOk()
            ->assertJsonPath('data.scope', 'guardian-contacts')
            ->assertJsonPath('data.summary.contactLogs', 2)
            ->assertJsonPath('data.summary.completed', 1)
            ->assertJsonPath('data.breakdowns.byMethod.0.channel', 'in_app');
    }

    public function test_report_dataset_endpoints_return_rows_and_columns(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'ana-admin-070', 'ana-admin-070@hfccf.org');
        Sanctum::actingAs($admin);

        $this->seedAnalyticsDataset($admin);

        $this->getJson('/api/preschool/analytics/reports/attendance')
            ->assertOk()
            ->assertJsonPath('data.report', 'attendance')
            ->assertJsonStructure(['data' => ['columns', 'rows', 'summary']]);

        $this->getJson('/api/preschool/analytics/reports/sessions')
            ->assertOk()
            ->assertJsonPath('data.report', 'sessions');

        $this->getJson('/api/preschool/analytics/reports/schedules')
            ->assertOk()
            ->assertJsonPath('data.report', 'schedules');
    }

    public function test_endpoints_are_read_only(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'ana-admin-080', 'ana-admin-080@hfccf.org');
        Sanctum::actingAs($admin);

        $this->postJson('/api/preschool/analytics/dashboard')
            ->assertStatus(405);
    }

    private function seedAnalyticsDataset(User $teacher, ?User $otherTeacher = null): array
    {
        foreach ([
            'preschool_attendance_records',
            'preschool_attendance_sessions',
            'preschool_guardian_communications',
            'preschool_class_students',
            'preschool_student_guardians',
            'preschool_schedule_entries',
            'preschool_classes',
            'preschool_students',
            'preschool_guardians',
        ] as $table) {
            DB::table($table)->delete();
        }

        $classOne = $this->createClass('PS-ANA-001', 'Analytics Class One', $teacher->id);
        $classTwo = $this->createClass('PS-ANA-002', 'Analytics Class Two', $otherTeacher?->id);
        $studentOne = $this->createStudent('PS-ANA-STU-001', 'Ari', 'Student');
        $studentTwo = $this->createStudent('PS-ANA-STU-002', 'Bora', 'Student');
        $guardianOne = $this->createGuardian('Guardian One', '+855 12 111 111', 'guardian.one@hfccf.org');
        $guardianTwo = $this->createGuardian('Guardian Two', '+855 12 222 222', 'guardian.two@hfccf.org');

        $this->linkGuardian($studentOne->id, $guardianOne->id, $teacher->id);
        $this->linkGuardian($studentTwo->id, $guardianTwo->id, $teacher->id);
        $this->attachStudentToClass($classOne->id, $studentOne->id);

        if ($otherTeacher) {
            $this->attachStudentToClass($classTwo->id, $studentTwo->id);
        } else {
            $this->attachStudentToClass($classOne->id, $studentTwo->id);
        }

        $dummySchedule = $this->createSchedule($classTwo->id, $otherTeacher?->id, now()->dayOfWeekIso, now()->subDay()->toDateString(), now()->addDays(7)->toDateString(), 'Room B1', 'Prep Time', 'inactive', $teacher->id);
        $schedule = $this->createSchedule($classOne->id, $teacher->id, now()->dayOfWeekIso, now()->subDay()->toDateString(), now()->addDays(7)->toDateString(), 'Room A1', 'Morning Circle', 'active', $teacher->id);
        $completedSession = $this->createSession($classOne->id, $schedule->id, now()->toDateString(), 'completed', true, $teacher->id, '08:00', '08:45');
        $missingSession = $this->createSession($classTwo->id, $dummySchedule->id, now()->toDateString(), 'open', true, $teacher->id, '08:00', '03:00');

        $this->createAttendanceRecord($classOne->id, $studentOne->id, $completedSession->id, now()->toDateString(), 'present', $teacher->id);
        $this->createAttendanceRecord($classOne->id, $studentTwo->id, $completedSession->id, now()->toDateString(), 'absent', $teacher->id);

        PreschoolGuardianCommunication::query()->create([
            'student_id' => $studentOne->id,
            'guardian_id' => $guardianOne->id,
            'source_type' => 'attendance',
            'source_id' => 'absence-streak-'.$studentOne->id,
            'communication_type' => 'repeated_absence',
            'channel' => 'in_app',
            'subject' => 'Repeated absence follow-up',
            'message' => 'Please review attendance.',
            'severity' => 'high',
            'status' => 'queued',
            'created_by' => $teacher->id,
        ]);

        PreschoolGuardianCommunication::query()->create([
            'student_id' => $studentTwo->id,
            'guardian_id' => $guardianTwo->id,
            'source_type' => 'attendance',
            'source_id' => 'absence-streak-'.$studentTwo->id,
            'communication_type' => 'repeated_absence',
            'channel' => 'in_app',
            'subject' => 'Repeated absence acknowledged',
            'message' => 'Acknowledged by guardian.',
            'severity' => 'high',
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
            'created_by' => $teacher->id,
        ]);

        return [
            'classOne' => $classOne,
            'classTwo' => $classTwo,
            'studentOne' => $studentOne,
            'studentTwo' => $studentTwo,
            'schedule' => $schedule,
            'completedSession' => $completedSession,
            'missingSession' => $missingSession,
        ];
    }

    private function makeUserWithRole(string $roleCode, string $id, string $email): User
    {
        $role = Role::query()->with('permissions')->findOrFail($roleCode);

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

    private function linkGuardian(int $studentId, int $guardianId, string|int $createdBy): void
    {
        PreschoolStudent::query()->findOrFail($studentId)->guardians()->syncWithoutDetaching([
            $guardianId => [
                'relationship_type' => 'mother',
                'is_primary' => true,
                'can_pickup' => true,
                'emergency_priority' => 1,
                'status' => 'active',
                'starts_at' => now()->toDateString(),
            ],
        ]);
    }

    private function attachStudentToClass(int $classId, int $studentId): void
    {
        DB::table('preschool_class_students')->updateOrInsert([
            'class_id' => $classId,
            'student_id' => $studentId,
        ], [
            'enrolled_at' => now(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSchedule(int $classId, ?string $teacherId, int $dayOfWeek, ?string $effectiveFrom, ?string $effectiveUntil, string $room, string $activityLabel, string $status, string|int $createdBy): PreschoolScheduleEntry
    {
        return PreschoolScheduleEntry::query()->create([
            'class_id' => $classId,
            'teacher_user_id' => $teacherId,
            'day_of_week' => $dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '09:00',
            'room' => $room,
            'activity_label' => $activityLabel,
            'notes' => null,
            'status' => $status,
            'effective_from' => $effectiveFrom,
            'effective_until' => $effectiveUntil,
            'academic_year_id' => null,
            'term_id' => null,
            'created_by_user_id' => $createdBy,
            'updated_by_user_id' => $createdBy,
        ]);
    }

    private function createSession(int $classId, ?int $scheduleId, string $date, string $status, bool $generatedFromSchedule, string|int $createdBy, string $startTime = '08:00', string $endTime = '09:00'): PreschoolAttendanceSession
    {
        return PreschoolAttendanceSession::query()->create([
            'preschool_class_id' => $classId,
            'schedule_id' => $scheduleId,
            'attendance_date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => $status,
            'generated_from_schedule' => $generatedFromSchedule,
            'notes' => null,
            'session_key' => sprintf('%s-%s-%s', $classId, $scheduleId ?? 'manual', $date),
            'created_by' => $createdBy,
        ]);
    }

    private function createAttendanceRecord(int $classId, int $studentId, int $sessionId, string $date, string $status, string|int $createdBy): PreschoolAttendanceRecord
    {
        return PreschoolAttendanceRecord::query()->create([
            'class_id' => $classId,
            'student_id' => $studentId,
            'attendance_session_id' => $sessionId,
            'recorded_by_user_id' => $createdBy,
            'attendance_date' => $date,
            'status' => $status,
            'note' => null,
            'academic_year_id' => null,
            'term_id' => null,
        ]);
    }
}
