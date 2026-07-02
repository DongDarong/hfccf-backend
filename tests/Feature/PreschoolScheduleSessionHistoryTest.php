<?php

namespace Tests\Feature;

use App\Models\PreschoolAttendanceRecord;
use App\Models\PreschoolAttendanceSession;
use App\Models\PreschoolClass;
use App\Models\PreschoolGuardianCommunication;
use App\Models\PreschoolScheduleEntry;
use App\Models\PreschoolStudent;
use App\Models\Role;
use App\Models\User;
use App\Support\PreschoolAttendanceSessionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolScheduleSessionHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_fetch_schedule_sessions(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-sch-hist-001', 'hist001@hfccf.org');
        Sanctum::actingAs($admin);

        $class = $this->createClass('PS-HIST-001', 'History Class');
        $schedule = $this->createSchedule($class->id, null, now()->dayOfWeekIso, now()->toDateString(), null, 'Room A1', 'Morning Circle', 'active', $admin->id);
        $session = $this->createSession($class->id, $schedule->id, now()->toDateString(), 'scheduled', true, $admin->id);

        $this->getJson("/api/preschool/schedules/{$schedule->id}/sessions")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.total', 1)
            ->assertJsonPath('data.items.0.id', $session->id);
    }

    public function test_teacher_can_fetch_assigned_schedule_sessions(): void
    {
        $teacher = $this->makeUserWithRole('teacher-preschool', 'psc-sch-hist-010', 'hist010@hfccf.org');
        Sanctum::actingAs($teacher);

        $class = $this->createClass('PS-HIST-010', 'Teacher History Class', $teacher->id);
        $schedule = $this->createSchedule($class->id, $teacher->id, now()->dayOfWeekIso, now()->toDateString(), null, 'Room B1', 'Circle Time', 'active', $teacher->id);
        $session = $this->createSession($class->id, $schedule->id, now()->toDateString(), 'open', true, $teacher->id);

        $this->getJson("/api/preschool/schedules/{$schedule->id}/sessions")
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $session->id);
    }

    public function test_teacher_cannot_fetch_unrelated_schedule_sessions(): void
    {
        $teacher = $this->makeUserWithRole('teacher-preschool', 'psc-sch-hist-020', 'hist020@hfccf.org');
        Sanctum::actingAs($teacher);

        $ownClass = $this->createClass('PS-HIST-020', 'Own History Class', $teacher->id);
        $ownSchedule = $this->createSchedule($ownClass->id, $teacher->id, now()->dayOfWeekIso, now()->toDateString(), null, 'Room C1', 'Music', 'active', $teacher->id);
        $this->createSession($ownClass->id, $ownSchedule->id, now()->toDateString(), 'scheduled', true, $teacher->id);

        $otherTeacher = $this->makeUserWithRole('teacher-preschool', 'psc-sch-hist-021', 'hist021@hfccf.org');
        $otherClass = $this->createClass('PS-HIST-021', 'Other History Class', $otherTeacher->id);
        $otherSchedule = $this->createSchedule($otherClass->id, $otherTeacher->id, now()->dayOfWeekIso, now()->toDateString(), null, 'Room C2', 'Art', 'active', $otherTeacher->id);
        $this->createSession($otherClass->id, $otherSchedule->id, now()->toDateString(), 'scheduled', true, $otherTeacher->id);

        $this->getJson("/api/preschool/schedules/{$otherSchedule->id}/sessions")
            ->assertForbidden();
    }

    public function test_today_session_returns_correct_session(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-sch-hist-030', 'hist030@hfccf.org');
        Sanctum::actingAs($admin);

        $class = $this->createClass('PS-HIST-030', 'Today History Class');
        $schedule = $this->createSchedule($class->id, null, now()->dayOfWeekIso, now()->toDateString(), null, 'Room D1', 'Reading', 'active', $admin->id);
        $session = $this->createSession($class->id, $schedule->id, now()->toDateString(), 'open', true, $admin->id);

        $this->getJson("/api/preschool/schedules/{$schedule->id}/today-session")
            ->assertOk()
            ->assertJsonPath('data.session.id', $session->id)
            ->assertJsonPath('data.session.status', 'open');
    }

    public function test_history_returns_schedule_today_recent_sessions_and_summary(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-sch-hist-040', 'hist040@hfccf.org');
        Sanctum::actingAs($admin);

        $class = $this->createClass('PS-HIST-040', 'Bridge History Class');
        $schedule = $this->createSchedule($class->id, null, now()->dayOfWeekIso, now()->subWeeks(4)->toDateString(), null, 'Room E1', 'Literacy', 'active', $admin->id);
        $student = $this->createStudent('PS-HIST-STU-040', 'Alert', 'Student');
        $this->attachStudentToClass($class->id, $student->id);

        $sessions = [
            $this->createSession($class->id, $schedule->id, now()->subWeeks(4)->toDateString(), 'scheduled', true, $admin->id),
            $this->createSession($class->id, $schedule->id, now()->subWeeks(3)->toDateString(), 'open', true, $admin->id),
            $this->createSession($class->id, $schedule->id, now()->subWeeks(2)->toDateString(), 'completed', true, $admin->id),
            $this->createSession($class->id, $schedule->id, now()->subWeeks(1)->toDateString(), 'locked', true, $admin->id),
            $this->createSession($class->id, $schedule->id, now()->toDateString(), 'cancelled', true, $admin->id),
        ];

        $this->createAttendanceRecord($class->id, $student->id, $sessions[1]->id, now()->subWeeks(3)->toDateString(), 'present', $admin->id);
        $this->createAttendanceRecord($class->id, $student->id, $sessions[1]->id, now()->subWeeks(3)->toDateString(), 'absent', $admin->id);
        $this->createAttendanceRecord($class->id, $student->id, $sessions[2]->id, now()->subWeeks(2)->toDateString(), 'present', $admin->id);
        $this->createAttendanceRecord($class->id, $student->id, $sessions[2]->id, now()->subWeeks(2)->toDateString(), 'late', $admin->id);

        $this->createRepeatedAbsenceCommunication($student->id, $admin->id);

        $this->getJson("/api/preschool/schedules/{$schedule->id}/history")
            ->assertOk()
            ->assertJsonPath('data.schedule.id', $schedule->id)
            ->assertJsonPath('data.todaySession.id', $sessions[4]->id)
            ->assertJsonCount(5, 'data.recentSessions')
            ->assertJsonPath('data.summary.total', 5)
            ->assertJsonPath('data.summary.scheduled', 1)
            ->assertJsonPath('data.summary.open', 1)
            ->assertJsonPath('data.summary.completed', 1)
            ->assertJsonPath('data.summary.locked', 1)
            ->assertJsonPath('data.summary.cancelled', 1)
            ->assertJsonPath('data.summary.missing', 0)
            ->assertJsonPath('data.summary.completionRate', 20)
            ->assertJsonPath('data.alerts.items.0.alertType', 'repeated_absence')
            ->assertJsonPath('data.guardianContacts.items.0.sourceType', 'attendance');
    }

    public function test_summary_status_counts_are_correct(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-sch-hist-050', 'hist050@hfccf.org');
        Sanctum::actingAs($admin);

        $class = $this->createClass('PS-HIST-050', 'Summary Class');
        $schedule = $this->createSchedule($class->id, null, now()->dayOfWeekIso, now()->subWeeks(4)->toDateString(), null, 'Room F1', 'Counting', 'active', $admin->id);

        $this->createSession($class->id, $schedule->id, now()->subWeeks(4)->toDateString(), 'scheduled', true, $admin->id);
        $this->createSession($class->id, $schedule->id, now()->subWeeks(3)->toDateString(), 'open', true, $admin->id);
        $this->createSession($class->id, $schedule->id, now()->subWeeks(2)->toDateString(), 'completed', true, $admin->id);
        $this->createSession($class->id, $schedule->id, now()->subWeeks(1)->toDateString(), 'locked', true, $admin->id);
        $this->createSession($class->id, $schedule->id, now()->toDateString(), 'cancelled', true, $admin->id);

        $this->getJson("/api/preschool/schedules/{$schedule->id}/sessions")
            ->assertOk()
            ->assertJsonPath('data.summary.total', 5)
            ->assertJsonPath('data.summary.scheduled', 1)
            ->assertJsonPath('data.summary.open', 1)
            ->assertJsonPath('data.summary.completed', 1)
            ->assertJsonPath('data.summary.locked', 1)
            ->assertJsonPath('data.summary.cancelled', 1)
            ->assertJsonPath('data.summary.missing', 0);
    }

    public function test_completion_rate_is_correct(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-sch-hist-060', 'hist060@hfccf.org');
        Sanctum::actingAs($admin);

        $class = $this->createClass('PS-HIST-060', 'Completion Class');
        $schedule = $this->createSchedule($class->id, null, now()->dayOfWeekIso, now()->subWeeks(4)->toDateString(), null, 'Room G1', 'Completion', 'active', $admin->id);

        $this->createSession($class->id, $schedule->id, now()->subWeeks(4)->toDateString(), 'scheduled', true, $admin->id);
        $this->createSession($class->id, $schedule->id, now()->subWeeks(3)->toDateString(), 'open', true, $admin->id);
        $this->createSession($class->id, $schedule->id, now()->subWeeks(2)->toDateString(), 'completed', true, $admin->id);
        $this->createSession($class->id, $schedule->id, now()->subWeeks(1)->toDateString(), 'locked', true, $admin->id);
        $this->createSession($class->id, $schedule->id, now()->toDateString(), 'cancelled', true, $admin->id);

        $this->getJson("/api/preschool/schedules/{$schedule->id}/history")
            ->assertOk()
            ->assertJsonPath('data.summary.completionRate', 20);
    }

    public function test_attendance_rate_is_correct_from_records(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-sch-hist-070', 'hist070@hfccf.org');
        Sanctum::actingAs($admin);

        $class = $this->createClass('PS-HIST-070', 'Attendance Class');
        $schedule = $this->createSchedule($class->id, null, now()->dayOfWeekIso, now()->subWeeks(2)->toDateString(), null, 'Room H1', 'Attendance', 'active', $admin->id);
        $student = $this->createStudent('PS-HIST-STU-070', 'Attend', 'Student');
        $this->attachStudentToClass($class->id, $student->id);

        $session = $this->createSession($class->id, $schedule->id, now()->subWeeks(2)->toDateString(), 'completed', true, $admin->id);
        $this->createAttendanceRecord($class->id, $student->id, $session->id, now()->subWeeks(2)->toDateString(), 'present', $admin->id);
        $this->createAttendanceRecord($class->id, $student->id, $session->id, now()->subWeeks(2)->toDateString(), 'absent', $admin->id);

        $this->getJson("/api/preschool/schedules/{$schedule->id}/history")
            ->assertOk()
            ->assertJsonPath('data.summary.attendanceRate', 50);
    }

    public function test_manual_sessions_with_null_schedule_id_are_not_attached(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-sch-hist-080', 'hist080@hfccf.org');
        Sanctum::actingAs($admin);

        $class = $this->createClass('PS-HIST-080', 'Manual Session Class');
        $schedule = $this->createSchedule($class->id, null, now()->dayOfWeekIso, now()->subWeeks(1)->toDateString(), null, 'Room I1', 'Manual Exclusion', 'active', $admin->id);

        $structured = $this->createSession($class->id, $schedule->id, now()->subWeeks(1)->toDateString(), 'open', true, $admin->id);
        $this->createSession($class->id, null, now()->subWeeks(1)->toDateString(), 'completed', false, $admin->id);

        $this->getJson("/api/preschool/schedules/{$schedule->id}/sessions")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $structured->id);
    }

    public function test_legacy_free_text_schedules_are_not_parsed_for_history(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-sch-hist-090', 'hist090@hfccf.org');
        Sanctum::actingAs($admin);

        $class = $this->createClass('PS-HIST-090', 'Legacy Schedule Class');
        $class->schedule = 'Mon-Fri 8:00 AM';
        $class->save();

        $schedule = $this->createSchedule($class->id, null, now()->dayOfWeekIso, now()->subWeeks(1)->toDateString(), null, 'Room J1', 'Structured Only', 'active', $admin->id);
        $this->createSession($class->id, $schedule->id, now()->subWeeks(1)->toDateString(), 'scheduled', true, $admin->id);

        $this->getJson("/api/preschool/schedules/{$schedule->id}/history")
            ->assertOk()
            ->assertJsonPath('data.schedule.classId', $class->id)
            ->assertJsonCount(1, 'data.recentSessions');
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

    private function createSchedule(
        int $classId,
        ?string $teacherId,
        int $dayOfWeek,
        string $effectiveFrom,
        ?string $effectiveUntil,
        ?string $room,
        string $activityLabel,
        string $status,
        ?string $createdByUserId,
    ): PreschoolScheduleEntry {
        return PreschoolScheduleEntry::query()->create([
            'class_id' => $classId,
            'teacher_user_id' => $teacherId,
            'day_of_week' => $dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '09:00',
            'room' => $room,
            'activity_label' => $activityLabel,
            'notes' => 'Seeded for test coverage.',
            'status' => $status,
            'effective_from' => $effectiveFrom,
            'effective_until' => $effectiveUntil,
            'created_by_user_id' => $createdByUserId,
            'updated_by_user_id' => $createdByUserId,
        ]);
    }

    private function createSession(
        int $classId,
        ?int $scheduleId,
        string $date,
        string $status,
        bool $generatedFromSchedule,
        ?string $createdByUserId,
    ): PreschoolAttendanceSession {
        return PreschoolAttendanceSession::query()->create([
            'preschool_class_id' => $classId,
            'schedule_id' => $scheduleId,
            'attendance_date' => $date,
            'start_time' => '08:00',
            'end_time' => '09:00',
            'status' => $status,
            'generated_from_schedule' => $generatedFromSchedule,
            'notes' => 'Seeded for test coverage.',
            'session_key' => 'session-'.$classId.'-'.($scheduleId ?? 'manual').'-'.$date.'-'.str()->random(8),
            'created_by' => $createdByUserId,
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

    private function createAttendanceRecord(
        int $classId,
        int $studentId,
        int $sessionId,
        string $date,
        string $status,
        string $recordedByUserId,
    ): PreschoolAttendanceRecord {
        return PreschoolAttendanceRecord::query()->create([
            'class_id' => $classId,
            'student_id' => $studentId,
            'attendance_session_id' => $sessionId,
            'recorded_by_user_id' => $recordedByUserId,
            'attendance_date' => $date,
            'status' => $status,
            'note' => null,
        ]);
    }

    private function createRepeatedAbsenceCommunication(int $studentId, string $createdByUserId): PreschoolGuardianCommunication
    {
        return PreschoolGuardianCommunication::query()->create([
            'student_id' => $studentId,
            'guardian_id' => null,
            'source_type' => 'attendance',
            'source_id' => 'absence-streak-'.$studentId,
            'communication_type' => 'repeated_absence',
            'channel' => 'in_app',
            'subject' => 'Repeated absence follow-up',
            'message' => 'Attendance follow-up for schedule history coverage.',
            'severity' => 'high',
            'status' => 'queued',
            'created_by' => $createdByUserId,
        ]);
    }
}
