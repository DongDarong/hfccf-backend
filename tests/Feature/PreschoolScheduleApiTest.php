<?php

namespace Tests\Feature;

use App\Models\PreschoolClass;
use App\Models\PreschoolScheduleEntry;
use App\Models\Role;
use App\Models\User;
use App\Support\PreschoolScheduleStatus;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolScheduleApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_create_preschool_schedule_and_view_admin_list(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-100', 'preschool.schedule100@hfccf.org');
        Sanctum::actingAs($admin);

        $class = $this->createPreschoolClass('PS-SCH-100', 'Schedule Class', null, null);
        $teacher = $this->makeUserWithRole('teacher-preschool', 'psc-101', 'preschool.schedule101@hfccf.org');
        $payload = $this->schedulePayload($class->id, $teacher->id, 'Room A1', 'Morning Literacy');

        $this->postJson('/api/preschool/schedules', $payload)
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.schedule.classId', $class->id)
            ->assertJsonPath('data.schedule.teacherUserId', $teacher->id)
            ->assertJsonPath('data.schedule.status', PreschoolScheduleStatus::ACTIVE);

        $this->getJson('/api/preschool/schedules')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.items.0.activityLabel', 'Morning Literacy');
    }

    public function test_teacher_can_view_own_schedule_but_cannot_manage_schedules(): void
    {
        $teacher = $this->makeUserWithRole('teacher-preschool', 'psc-110', 'preschool.schedule110@hfccf.org');
        Sanctum::actingAs($teacher);

        $class = $this->createPreschoolClass('PS-SCH-110', 'Teacher Class', $teacher->id, trim($teacher->first_name.' '.$teacher->last_name));
        $schedule = $this->createSchedule($class->id, $teacher->id, 1, '08:00', '09:00', 'Room B1', 'Circle Time', PreschoolScheduleStatus::ACTIVE, $teacher->id);

        $this->getJson('/api/preschool/me/schedule')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.items.0.id', $schedule->id);

        $this->postJson('/api/preschool/schedules', $this->schedulePayload($class->id, $teacher->id))
            ->assertForbidden();
    }

    public function test_teacher_overlap_conflict_is_blocked(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-120', 'preschool.schedule120@hfccf.org');
        Sanctum::actingAs($admin);

        $teacher = $this->makeUserWithRole('teacher-preschool', 'psc-121', 'preschool.schedule121@hfccf.org');
        $classA = $this->createPreschoolClass('PS-SCH-121', 'Overlap Class A', $teacher->id, trim($teacher->first_name.' '.$teacher->last_name));
        $classB = $this->createPreschoolClass('PS-SCH-122', 'Overlap Class B', $teacher->id, trim($teacher->first_name.' '.$teacher->last_name));

        $this->createSchedule($classA->id, $teacher->id, 1, '09:00', '10:00', 'Room C1', 'Story Time', PreschoolScheduleStatus::ACTIVE, $admin->id);

        $this->postJson('/api/preschool/schedules', $this->schedulePayload($classB->id, $teacher->id, 'Room C2', 'Music Time', 1, '09:30', '10:30'))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.conflicts.0.type', 'teacher');
    }

    public function test_class_overlap_conflict_is_blocked(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-130', 'preschool.schedule130@hfccf.org');
        Sanctum::actingAs($admin);

        $teacherOne = $this->makeUserWithRole('teacher-preschool', 'psc-131', 'preschool.schedule131@hfccf.org');
        $teacherTwo = $this->makeUserWithRole('teacher-preschool', 'psc-132', 'preschool.schedule132@hfccf.org');
        $class = $this->createPreschoolClass('PS-SCH-130', 'Conflict Class', $teacherOne->id, trim($teacherOne->first_name.' '.$teacherOne->last_name));

        $this->createSchedule($class->id, $teacherOne->id, 2, '10:00', '11:00', 'Room D1', 'Outdoor Play', PreschoolScheduleStatus::ACTIVE, $admin->id);

        $this->postJson('/api/preschool/schedules', $this->schedulePayload($class->id, $teacherTwo->id, 'Room D2', 'Math Games', 2, '10:30', '11:30'))
            ->assertStatus(422)
            ->assertJsonPath('data.conflicts.0.type', 'class');
    }

    public function test_room_overlap_conflict_is_blocked(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-140', 'preschool.schedule140@hfccf.org');
        Sanctum::actingAs($admin);

        $teacherOne = $this->makeUserWithRole('teacher-preschool', 'psc-141', 'preschool.schedule141@hfccf.org');
        $teacherTwo = $this->makeUserWithRole('teacher-preschool', 'psc-142', 'preschool.schedule142@hfccf.org');
        $classA = $this->createPreschoolClass('PS-SCH-141', 'Room Class A', $teacherOne->id, trim($teacherOne->first_name.' '.$teacherOne->last_name));
        $classB = $this->createPreschoolClass('PS-SCH-142', 'Room Class B', $teacherTwo->id, trim($teacherTwo->first_name.' '.$teacherTwo->last_name));

        $this->createSchedule($classA->id, $teacherOne->id, 3, '11:00', '12:00', 'Room E1', 'Center Time', PreschoolScheduleStatus::ACTIVE, $admin->id);

        $this->postJson('/api/preschool/schedules', $this->schedulePayload($classB->id, $teacherTwo->id, 'Room E1', 'Science Time', 3, '11:30', '12:30'))
            ->assertStatus(422)
            ->assertJsonPath('data.conflicts.0.type', 'room');
    }

    public function test_inactive_schedules_do_not_block_conflicts(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-150', 'preschool.schedule150@hfccf.org');
        Sanctum::actingAs($admin);

        $teacher = $this->makeUserWithRole('teacher-preschool', 'psc-151', 'preschool.schedule151@hfccf.org');
        $class = $this->createPreschoolClass('PS-SCH-150', 'Inactive Class', $teacher->id, trim($teacher->first_name.' '.$teacher->last_name));

        $this->createSchedule($class->id, $teacher->id, 4, '13:00', '14:00', 'Room F1', 'Snack Time', PreschoolScheduleStatus::INACTIVE, $admin->id);

        $this->postJson('/api/preschool/schedules', $this->schedulePayload($class->id, $teacher->id, 'Room F1', 'Snack Time', 4, '13:00', '14:00'))
            ->assertCreated()
            ->assertJsonPath('success', true);
    }

    public function test_update_ignores_same_schedule_record_in_conflict_checks(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-160', 'preschool.schedule160@hfccf.org');
        Sanctum::actingAs($admin);

        $teacher = $this->makeUserWithRole('teacher-preschool', 'psc-161', 'preschool.schedule161@hfccf.org');
        $class = $this->createPreschoolClass('PS-SCH-160', 'Update Class', $teacher->id, trim($teacher->first_name.' '.$teacher->last_name));
        $schedule = $this->createSchedule($class->id, $teacher->id, 5, '14:00', '15:00', 'Room G1', 'Art Time', PreschoolScheduleStatus::ACTIVE, $admin->id);

        $this->patchJson("/api/preschool/schedules/{$schedule->id}", [
            'notes' => 'Updated note only.',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.schedule.notes', 'Updated note only.');
    }

    public function test_unauthorized_users_are_blocked_from_schedule_management(): void
    {
        $coach = $this->makeUserWithRole('coach', 'psc-170', 'preschool.schedule170@hfccf.org');
        Sanctum::actingAs($coach);

        $this->getJson('/api/preschool/schedules')
            ->assertForbidden();
    }

    public function test_teacher_cannot_access_unrelated_teacher_or_class_schedule(): void
    {
        $teacher = $this->makeUserWithRole('teacher-preschool', 'psc-180', 'preschool.schedule180@hfccf.org');
        Sanctum::actingAs($teacher);

        $ownClass = $this->createPreschoolClass('PS-SCH-180', 'Own Class', $teacher->id, trim($teacher->first_name.' '.$teacher->last_name));
        $otherTeacher = $this->makeUserWithRole('teacher-preschool', 'psc-181', 'preschool.schedule181@hfccf.org');
        $otherClass = $this->createPreschoolClass('PS-SCH-181', 'Other Class', $otherTeacher->id, trim($otherTeacher->first_name.' '.$otherTeacher->last_name));

        $this->getJson("/api/preschool/classes/{$ownClass->id}/schedule")
            ->assertOk();

        $this->getJson("/api/preschool/classes/{$otherClass->id}/schedule")
            ->assertForbidden();

        $this->getJson("/api/preschool/teachers/{$otherTeacher->id}/schedule")
            ->assertForbidden();
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

    private function createPreschoolClass(string $code, string $name, ?string $teacherId = null, ?string $teacherDisplayName = null): PreschoolClass
    {
        return PreschoolClass::query()->create([
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
        ]);
    }

    private function createSchedule(
        int $classId,
        ?string $teacherId,
        int $dayOfWeek,
        string $startTime,
        string $endTime,
        ?string $room,
        string $activityLabel,
        string $status,
        ?string $createdByUserId,
    ): PreschoolScheduleEntry {
        return PreschoolScheduleEntry::query()->create([
            'class_id' => $classId,
            'teacher_user_id' => $teacherId,
            'day_of_week' => $dayOfWeek,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'room' => $room,
            'activity_label' => $activityLabel,
            'notes' => 'Seeded for test coverage.',
            'status' => $status,
            'created_by_user_id' => $createdByUserId,
            'updated_by_user_id' => $createdByUserId,
        ]);
    }

    private function schedulePayload(
        int $classId,
        ?string $teacherId,
        ?string $room = 'Room Z1',
        string $activityLabel = 'Morning Circle',
        int $dayOfWeek = 1,
        string $startTime = '08:00',
        string $endTime = '09:00',
    ): array {
        return [
            'class_id' => $classId,
            'teacher_user_id' => $teacherId,
            'day_of_week' => $dayOfWeek,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'room' => $room,
            'activity_label' => $activityLabel,
            'notes' => 'Test timetable entry.',
            'status' => PreschoolScheduleStatus::ACTIVE,
            'effective_from' => '2026-05-20',
            'effective_until' => '2026-06-20',
        ];
    }
}
