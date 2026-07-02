<?php

namespace Tests\Feature\Preschool;

use App\Models\Department;
use App\Models\PreschoolAttendanceSession;
use App\Models\PreschoolClass;
use App\Models\PreschoolScheduleEntry;
use App\Models\PreschoolStudent;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolAttendanceSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
        Carbon::setTestNow(Carbon::parse('2026-07-01 08:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_generate_session_from_schedule_for_valid_day(): void
    {
        $admin = User::factory()->asAdminPreschool()->create();
        Sanctum::actingAs($admin);

        $class = $this->createClass();
        $schedule = $this->createSchedule($class, Carbon::now()->isoWeekday());

        $response = $this->postJson('/api/preschool/attendance-sessions/generate', [
            'date' => Carbon::now()->toDateString(),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.generatedCount', 1);

        $this->assertDatabaseHas('preschool_attendance_sessions', [
            'preschool_class_id' => $class->id,
            'schedule_id' => $schedule->id,
            'attendance_date' => Carbon::now()->startOfDay()->toDateTimeString(),
            'generated_from_schedule' => 1,
        ]);
    }

    public function test_does_not_generate_session_for_non_scheduled_day(): void
    {
        $admin = User::factory()->asAdminPreschool()->create();
        Sanctum::actingAs($admin);

        $class = $this->createClass();
        $this->createSchedule($class, $this->nextIsoWeekday(Carbon::now()->isoWeekday()));

        $response = $this->postJson('/api/preschool/attendance-sessions/generate', [
            'date' => Carbon::now()->toDateString(),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.generatedCount', 0);

        $this->assertDatabaseCount('preschool_attendance_sessions', 0);
    }

    public function test_does_not_duplicate_sessions(): void
    {
        $admin = User::factory()->asAdminPreschool()->create();
        Sanctum::actingAs($admin);

        $class = $this->createClass();
        $this->createSchedule($class, Carbon::now()->isoWeekday());

        $this->postJson('/api/preschool/attendance-sessions/generate', [
            'date' => Carbon::now()->toDateString(),
        ])->assertOk();

        $this->postJson('/api/preschool/attendance-sessions/generate', [
            'date' => Carbon::now()->toDateString(),
        ])->assertOk();

        $this->assertDatabaseCount('preschool_attendance_sessions', 1);
    }

    public function test_manual_session_creation_works(): void
    {
        $admin = User::factory()->asAdminPreschool()->create();
        Sanctum::actingAs($admin);

        $class = $this->createClass();

        $response = $this->postJson('/api/preschool/attendance-sessions', [
            'class_id' => $class->id,
            'attendance_date' => Carbon::now()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '11:00',
            'notes' => 'Manual backup session',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.attendanceSession.classId', $class->id)
            ->assertJsonPath('data.attendanceSession.status', 'open')
            ->assertJsonPath('data.attendanceSession.generatedFromSchedule', false);

        $this->assertDatabaseHas('preschool_attendance_sessions', [
            'preschool_class_id' => $class->id,
            'attendance_date' => Carbon::now()->startOfDay()->toDateTimeString(),
            'status' => 'open',
            'generated_from_schedule' => 0,
        ]);
    }

    public function test_save_attendance_records_under_session(): void
    {
        $admin = User::factory()->asAdminPreschool()->create();
        Sanctum::actingAs($admin);

        $class = $this->createClass();
        $session = PreschoolAttendanceSession::query()->create([
            'preschool_class_id' => $class->id,
            'attendance_date' => Carbon::now()->toDateString(),
            'status' => 'open',
            'generated_from_schedule' => false,
            'session_key' => 'class:'.$class->id.':'.Carbon::now()->toDateString().':manual',
        ]);
        $student = $this->createStudent();

        $response = $this->postJson('/api/preschool/attendance-sessions/'.$session->id.'/records', [
            'records' => [
                [
                    'student_id' => $student->id,
                    'status' => 'present',
                    'note' => 'On time',
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.attendanceRecords.0.attendanceSessionId', $session->id)
            ->assertJsonPath('data.attendanceRecords.0.status', 'present');

        $this->assertDatabaseHas('preschool_attendance_records', [
            'attendance_session_id' => $session->id,
            'class_id' => $class->id,
            'student_id' => $student->id,
            'status' => 'present',
        ]);
    }

    public function test_legacy_attendance_save_still_works(): void
    {
        $admin = User::factory()->asAdminPreschool()->create();
        Sanctum::actingAs($admin);

        $class = $this->createClass();
        $student = $this->createStudent();

        $response = $this->postJson('/api/preschool/attendance', [
            'class_id' => $class->id,
            'student_id' => $student->id,
            'attendance_date' => Carbon::now()->toDateString(),
            'status' => 'absent',
            'note' => 'Legacy route',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.attendance.classId', $class->id)
            ->assertJsonPath('data.attendance.attendanceSessionId', null);

        $this->assertDatabaseHas('preschool_attendance_records', [
            'class_id' => $class->id,
            'student_id' => $student->id,
            'attendance_date' => Carbon::now()->startOfDay()->toDateTimeString(),
            'status' => 'absent',
            'attendance_session_id' => null,
        ]);
    }

    public function test_session_lifecycle_complete_lock_and_reopen_works(): void
    {
        $admin = User::factory()->asAdminPreschool()->create();
        Sanctum::actingAs($admin);

        $session = PreschoolAttendanceSession::query()->create([
            'preschool_class_id' => $this->createClass()->id,
            'attendance_date' => Carbon::now()->toDateString(),
            'status' => 'open',
            'generated_from_schedule' => false,
            'session_key' => 'class:manual:'.Carbon::now()->toDateString(),
        ]);

        $this->patchJson('/api/preschool/attendance-sessions/'.$session->id.'/close')
            ->assertOk()
            ->assertJsonPath('data.attendanceSession.status', 'completed');

        $this->patchJson('/api/preschool/attendance-sessions/'.$session->id.'/lock')
            ->assertOk()
            ->assertJsonPath('data.attendanceSession.status', 'locked');

        $this->patchJson('/api/preschool/attendance-sessions/'.$session->id.'/reopen')
            ->assertOk()
            ->assertJsonPath('data.attendanceSession.status', 'open');

        $this->assertDatabaseHas('preschool_attendance_sessions', [
            'id' => $session->id,
            'status' => 'open',
        ]);
    }

    public function test_scheduled_session_can_open(): void
    {
        $teacher = User::factory()->asTeacherPreschool()->create();
        Sanctum::actingAs($teacher);

        $class = $this->createClass($teacher);
        $session = PreschoolAttendanceSession::query()->create([
            'preschool_class_id' => $class->id,
            'attendance_date' => Carbon::now()->toDateString(),
            'status' => 'scheduled',
            'generated_from_schedule' => true,
            'session_key' => 'class:'.$class->id.':'.Carbon::now()->toDateString().':manual',
        ]);

        $this->patchJson('/api/preschool/attendance-sessions/'.$session->id.'/open')
            ->assertOk()
            ->assertJsonPath('data.attendanceSession.status', 'open');
    }

    public function test_cancelled_session_does_not_count_as_missing(): void
    {
        $admin = User::factory()->asAdminPreschool()->create();
        Sanctum::actingAs($admin);

        $class = $this->createClass();
        PreschoolAttendanceSession::query()->create([
            'preschool_class_id' => $class->id,
            'attendance_date' => Carbon::now()->subDay()->toDateString(),
            'status' => 'cancelled',
            'generated_from_schedule' => false,
            'session_key' => 'class:'.$class->id.':'.Carbon::now()->subDay()->toDateString().':manual',
        ]);

        $response = $this->getJson('/api/preschool/attendance-sessions/missing');

        $response->assertOk()
            ->assertJsonPath('data.count', 0);
    }

    public function test_missing_sessions_endpoint_returns_past_scheduled_open_sessions(): void
    {
        $admin = User::factory()->asAdminPreschool()->create();
        Sanctum::actingAs($admin);

        $class = $this->createClass();
        PreschoolAttendanceSession::query()->create([
            'preschool_class_id' => $class->id,
            'attendance_date' => Carbon::now()->subDay()->toDateString(),
            'status' => 'scheduled',
            'generated_from_schedule' => true,
            'session_key' => 'class:'.$class->id.':'.Carbon::now()->subDay()->toDateString().':manual',
        ]);
        PreschoolAttendanceSession::query()->create([
            'preschool_class_id' => $class->id,
            'attendance_date' => Carbon::now()->subDay()->toDateString(),
            'status' => 'open',
            'generated_from_schedule' => false,
            'session_key' => 'class:'.$class->id.':'.Carbon::now()->subDay()->toDateString().':manual-2',
        ]);

        $response = $this->getJson('/api/preschool/attendance-sessions/missing');

        $response->assertOk()
            ->assertJsonPath('data.count', 2);
    }

    public function test_today_sessions_endpoint_returns_expected_data(): void
    {
        $admin = User::factory()->asAdminPreschool()->create();
        Sanctum::actingAs($admin);

        $class = $this->createClass();
        PreschoolAttendanceSession::query()->create([
            'preschool_class_id' => $class->id,
            'attendance_date' => Carbon::now()->toDateString(),
            'status' => 'open',
            'generated_from_schedule' => false,
            'session_key' => 'class:'.$class->id.':'.Carbon::now()->toDateString().':manual',
        ]);
        PreschoolAttendanceSession::query()->create([
            'preschool_class_id' => $class->id,
            'attendance_date' => Carbon::now()->addDay()->toDateString(),
            'status' => 'open',
            'generated_from_schedule' => false,
            'session_key' => 'class:'.$class->id.':'.Carbon::now()->addDay()->toDateString().':manual',
        ]);

        $response = $this->getJson('/api/preschool/attendance-sessions?date='.Carbon::now()->toDateString());

        $response->assertOk()
            ->assertJsonPath('data.pagination.total', 1);
    }

    public function test_teacher_permissions_are_correct(): void
    {
        $teacher = User::factory()->asTeacherPreschool()->create();
        Sanctum::actingAs($teacher);

        $class = $this->createClass($teacher);
        $session = PreschoolAttendanceSession::query()->create([
            'preschool_class_id' => $class->id,
            'attendance_date' => Carbon::now()->toDateString(),
            'status' => 'open',
            'generated_from_schedule' => false,
            'session_key' => 'class:'.$class->id.':'.Carbon::now()->toDateString().':manual',
        ]);
        $student = $this->createStudent();

        $this->getJson('/api/preschool/attendance-sessions?date='.Carbon::now()->toDateString())
            ->assertOk();

        $this->postJson('/api/preschool/attendance-sessions/generate', [
            'date' => Carbon::now()->toDateString(),
        ])->assertForbidden();

        $this->postJson('/api/preschool/attendance-sessions/'.$session->id.'/records', [
            'student_id' => $student->id,
            'status' => 'present',
        ])->assertOk();
    }

    private function seedReferenceData(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        try {
            DB::table('departments')->updateOrInsert(
                ['code' => 'education'],
                [
                    'name' => 'Education',
                    'display_order' => 1,
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );

            DB::table('roles')->updateOrInsert(
                ['code' => 'adminpreschool'],
                [
                    'name' => 'Admin Preschool',
                    'scope' => 'admin',
                    'domain_code' => 'preschool',
                    'department_code' => 'education',
                    'sort_order' => 10,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );

            DB::table('roles')->updateOrInsert(
                ['code' => 'teacher-preschool'],
                [
                    'name' => 'Teacher Preschool',
                    'scope' => 'staff',
                    'domain_code' => 'preschool',
                    'department_code' => 'education',
                    'sort_order' => 20,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        } finally {
            DB::statement('PRAGMA foreign_keys = ON');
        }
    }

    private function createClass(?User $teacher = null): PreschoolClass
    {
        return PreschoolClass::query()->create([
            'code' => 'PS-'.fake()->unique()->numerify('###'),
            'name' => fake()->unique()->word().' Class',
            'teacher_user_id' => $teacher?->id,
            'teacher_display_name' => $teacher ? trim($teacher->first_name.' '.$teacher->last_name) : null,
            'level' => 'Preschool',
            'schedule' => null,
            'students_count' => 0,
            'tuition_fee' => '0.00',
            'status' => 'active',
            'room' => 'Room 1',
            'notes' => null,
        ]);
    }

    private function createSchedule(PreschoolClass $class, int $dayOfWeek): PreschoolScheduleEntry
    {
        return PreschoolScheduleEntry::query()->create([
            'class_id' => $class->id,
            'teacher_user_id' => $class->teacher_user_id,
            'day_of_week' => $dayOfWeek,
            'start_time' => '08:00:00',
            'end_time' => '11:00:00',
            'room' => 'Room 1',
            'activity_label' => 'Morning Class',
            'notes' => 'Generated from schedule',
            'status' => 'active',
            'effective_from' => Carbon::now()->subMonth()->toDateString(),
            'effective_until' => Carbon::now()->addMonth()->toDateString(),
        ]);
    }

    private function createStudent(): PreschoolStudent
    {
        return PreschoolStudent::query()->create([
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'gender' => 'male',
            'date_of_birth' => Carbon::now()->subYears(4)->toDateString(),
            'guardian_name' => fake()->name(),
            'guardian_phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'status' => 'active',
            'student_type' => 'regular',
            'avatar' => null,
        ]);
    }

    private function nextIsoWeekday(int $dayOfWeek): int
    {
        return $dayOfWeek === 7 ? 1 : $dayOfWeek + 1;
    }
}







