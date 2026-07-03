<?php

namespace Tests\Feature;

use App\Models\PreschoolClass;
use App\Models\PreschoolNotification;
use App\Models\PreschoolStudent;
use App\Models\Role;
use App\Models\User;
use App\Services\PreschoolNotificationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_notifications_are_scoped_and_support_mark_read_and_archive(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-notif-001', 'notif001@hfccf.org');
        $teacher = $this->makeUserWithRole('teacher-preschool', 'psc-notif-002', 'notif002@hfccf.org');
        $otherTeacher = $this->makeUserWithRole('teacher-preschool', 'psc-notif-003', 'notif003@hfccf.org');
        Sanctum::actingAs($teacher);

        $class = $this->createClass('PS-NOTIF-001', 'Notification Class', $teacher->id);
        $student = $this->createStudent('PS-NOTIF-001', 'Nina', 'Student');
        $this->attachStudentToClass($class->id, $student->id);

        $targeted = PreschoolNotification::query()->create([
            'notification_type' => 'attendance.follow_up',
            'title' => 'Attendance follow-up required',
            'body' => 'Contact the guardian about repeated absence.',
            'severity' => 'high',
            'status' => PreschoolNotification::STATUS_UNREAD,
            'target_user_id' => $teacher->id,
            'target_role' => 'adminpreschool',
            'source_type' => 'attendance_communication',
            'source_id' => 'comm-1',
            'preschool_student_id' => $student->id,
            'preschool_class_id' => $class->id,
            'action_route' => 'dashboard-preschool-admin-notifications',
            'action_params' => ['studentId' => $student->id],
            'created_by' => $admin->id,
        ]);

        PreschoolNotification::query()->create([
            'notification_type' => 'payments.overdue_invoice',
            'title' => 'Overdue invoice',
            'body' => 'Review the payment status.',
            'severity' => 'medium',
            'status' => PreschoolNotification::STATUS_READ,
            'target_role' => 'adminpreschool',
            'source_type' => 'invoice',
            'source_id' => 'inv-1',
            'preschool_student_id' => $student->id,
            'preschool_class_id' => $class->id,
            'action_route' => 'dashboard-preschool-admin-notifications',
            'action_params' => [],
            'read_at' => now(),
            'created_by' => $admin->id,
        ]);

        $response = $this->getJson('/api/preschool/notifications');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.total', 1)
            ->assertJsonPath('data.summary.unread', 1)
            ->assertJsonPath('data.summary.read', 0)
            ->assertJsonPath('data.items.0.id', $targeted->id)
            ->assertJsonPath('data.items.0.notificationType', 'attendance.follow_up');

        $this->getJson('/api/preschool/notifications/summary')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.unread', 1)
            ->assertJsonPath('data.byType.0.total', 1);

        $this->patchJson('/api/preschool/notifications/'.$targeted->id.'/read')
            ->assertOk()
            ->assertJsonPath('data.notification.status', 'read');

        $this->patchJson('/api/preschool/notifications/'.$targeted->id.'/archive')
            ->assertOk()
            ->assertJsonPath('data.notification.status', 'archived');

        $this->actingAs($otherTeacher, 'sanctum');

        $this->getJson('/api/preschool/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'data.items');

        $this->patchJson('/api/preschool/notifications/'.$targeted->id.'/read')
            ->assertStatus(422);

        $adminResponse = $this->actingAs($admin, 'sanctum')->getJson('/api/preschool/notifications?status=all');
        $adminResponse
            ->assertOk()
            ->assertJsonCount(2, 'data.items');
    }

    public function test_notification_service_deduplicates_by_source_and_target(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psc-notif-010', 'notif010@hfccf.org');
        $service = app(PreschoolNotificationService::class);

        $first = $service->upsertNotification([
            'notification_type' => 'attendance.follow_up',
            'title' => 'Attendance follow-up required',
            'body' => 'Contact the guardian about repeated absence.',
            'severity' => 'high',
            'target_user_id' => $admin->id,
            'target_role' => 'adminpreschool',
            'source_type' => 'attendance_communication',
            'source_id' => 'comm-dedupe-1',
            'created_by' => $admin->id,
        ]);

        $second = $service->upsertNotification([
            'notification_type' => 'attendance.follow_up',
            'title' => 'Attendance follow-up required',
            'body' => 'Contact the guardian about repeated absence.',
            'severity' => 'high',
            'target_user_id' => $admin->id,
            'target_role' => 'adminpreschool',
            'source_type' => 'attendance_communication',
            'source_id' => 'comm-dedupe-1',
            'created_by' => $admin->id,
        ]);

        $this->assertTrue($first['created']);
        $this->assertFalse($second['created']);
        $this->assertSame(1, PreschoolNotification::query()->count());
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
