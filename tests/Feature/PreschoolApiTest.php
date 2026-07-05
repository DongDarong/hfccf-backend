<?php

namespace Tests\Feature;

use App\Models\PreschoolAttendanceRecord;
use App\Models\PreschoolGuardian;
use App\Models\PreschoolGuardianCommunication;
use App\Models\Role;
use App\Models\PreschoolStudentGuardian;
use App\Models\User;
use App\Support\PreschoolAttendanceConfigurationService;
use App\Services\PreschoolGuardianCommunicationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_adminpreschool_can_manage_preschool_classes(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_500', 'preschool.admin500@hfccf.org');
        Sanctum::actingAs($admin);

        $teacher = $this->makeUserWithRole('teacher-preschool', 'usr_501', 'teacher.preschool500@hfccf.org');

        $create = $this->postJson('/api/preschool/classes', [
            'code' => 'PS-NUR-001',
            'name' => 'Morning Stars',
            'teacher_user_id' => $teacher->id,
            'teacher_display_name' => trim($teacher->first_name.' '.$teacher->last_name),
            'level' => 'Nursery',
            'schedule' => 'Mon-Fri 8:00 AM',
            'students_count' => 0,
            'status' => 'active',
            'room' => 'Room A1',
            'notes' => 'Created by test',
        ]);

        $create
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.class.code', 'PS-NUR-001')
            ->assertJsonPath('data.class.teacherUserId', $teacher->id);

        $classId = $create->json('data.class.id');

        $this->assertDatabaseHas('preschool_classes', [
            'id' => $classId,
            'code' => 'PS-NUR-001',
            'teacher_user_id' => $teacher->id,
        ]);

        $this->getJson('/api/preschool/classes?page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'items',
                    'pagination' => ['page', 'perPage', 'total', 'totalPages'],
                ],
            ]);

        $this->putJson('/api/preschool/classes/'.$classId, [
            'name' => 'Morning Stars Updated',
            'status' => 'pending',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.class.name', 'Morning Stars Updated')
            ->assertJsonPath('data.class.status', 'pending');

        $this->deleteJson('/api/preschool/classes/'.$classId)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('preschool_classes', [
            'id' => $classId,
        ]);
    }

    public function test_adminpreschool_can_manage_preschool_students(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_510', 'preschool.admin510@hfccf.org');
        Sanctum::actingAs($admin);

        $create = $this->postJson('/api/preschool/students', [
            'student_code' => 'PS-STU-900',
            'first_name' => 'Dara',
            'last_name' => 'Sok',
            'gender' => 'female',
            'date_of_birth' => '2020-01-15',
            'guardian_name' => 'Sok Vannak',
            'guardian_phone' => '+855 12 900 900',
            'address' => 'Phnom Penh',
            'status' => 'active',
        ]);

        $create
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.student.studentCode', 'PS-STU-900');

        $studentId = $create->json('data.student.id');

        $this->assertDatabaseHas('preschool_students', [
            'id' => $studentId,
            'student_code' => 'PS-STU-900',
        ]);

        $this->getJson('/api/preschool/students?page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->putJson('/api/preschool/students/'.$studentId, [
            'guardian_phone' => '+855 12 911 911',
            'status' => 'pending',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.student.status', 'pending');

        $this->deleteJson('/api/preschool/students/'.$studentId)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('preschool_students', [
            'id' => $studentId,
        ]);
    }

    public function test_adminpreschool_can_manage_preschool_payments(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_520', 'preschool.admin520@hfccf.org');
        Sanctum::actingAs($admin);

        $class = $this->createPreschoolClass('PS-CLASS-520', 'Payment Class');
        $student = $this->createPreschoolStudent('PS-STU-520', 'Payment', 'Student');

        $create = $this->postJson('/api/preschool/payments', [
            'student_id' => $student->id,
            'class_id' => $class->id,
            'payment_reference' => 'PAY-TEST-001',
            'amount' => 45,
            'currency' => 'USD',
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'due_date' => '2026-05-20',
            'note' => 'Test payment',
        ]);

        $create
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment.paymentReference', 'PAY-TEST-001');

        $paymentId = $create->json('data.payment.id');

        $this->assertDatabaseHas('preschool_payments', [
            'id' => $paymentId,
            'payment_reference' => 'PAY-TEST-001',
        ]);

        $this->getJson('/api/preschool/payments?page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->putJson('/api/preschool/payments/'.$paymentId, [
            'payment_status' => 'paid',
            'paid_at' => '2026-05-14 10:00:00',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment.paymentStatus', 'paid');

        $this->deleteJson('/api/preschool/payments/'.$paymentId)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('preschool_payments', [
            'id' => $paymentId,
        ]);
    }

    public function test_adminpreschool_can_manage_preschool_teachers(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_525', 'preschool.admin525@hfccf.org');
        Sanctum::actingAs($admin);

        $create = $this->postJson('/api/preschool/teachers', [
            'name' => 'Sokha Dara',
            'email' => 'teacher.preschool525@hfccf.org',
            'phone' => '+855 12 525 525',
            'status' => 'active',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ]);

        $create
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.role', 'teacher-preschool')
            ->assertJsonPath('data.user.departmentCode', 'education');

        $teacherId = $create->json('data.user.id');

        $this->assertDatabaseHas('users', [
            'id' => $teacherId,
            'email' => 'teacher.preschool525@hfccf.org',
            'role_code' => 'teacher-preschool',
            'department_code' => 'education',
        ]);

        $this->assertDatabaseHas('user_permissions', [
            'user_id' => $teacherId,
            'permission_code' => 'dashboard:read',
        ]);

        $class = $this->createPreschoolClass('PS-CLASS-525', 'Teacher Sync Class', $teacherId, 'Sokha Dara');

        $this->putJson('/api/preschool/teachers/'.$teacherId, [
            'name' => 'Sokha Dara Updated',
            'phone' => '+855 12 526 526',
            'status' => 'pending',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.status', 'pending');

        $this->deleteJson('/api/preschool/teachers/'.$teacherId)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('users', [
            'id' => $teacherId,
        ]);

        $this->assertDatabaseHas('preschool_classes', [
            'id' => $class->id,
            'teacher_user_id' => null,
            'teacher_display_name' => 'Sokha Dara',
        ]);
    }

    public function test_preschool_teacher_listing_includes_role_assigned_teachers_even_if_department_drifted(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_526', 'preschool.admin526@hfccf.org');
        Sanctum::actingAs($admin);

        $teacher = User::query()->create([
            'id' => 'usr_5261',
            'first_name' => 'Vannak',
            'last_name' => 'Lim',
            'username' => 'Vannak Lim Drifted',
            'email' => 'teacher.preschool526@hfccf.org',
            'phone' => '+855 12 526 526',
            'role_code' => 'teacher-preschool',
            'department_code' => 'sports',
            'status' => 'active',
            'password' => 'secret-pass',
        ]);

        $this->syncPermissions($teacher, Role::query()->findOrFail('teacher-preschool'));

        $response = $this->getJson('/api/preschool/teachers?page=1&per_page=10');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment([
                'email' => 'teacher.preschool526@hfccf.org',
                'role' => 'teacher-preschool',
            ]);
    }

    public function test_adminpreschool_can_record_attendance(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_530', 'preschool.admin530@hfccf.org');
        Sanctum::actingAs($admin);

        $class = $this->createPreschoolClass('PS-CLASS-530', 'Attendance Class');
        $student = $this->createPreschoolStudent('PS-STU-530', 'Attendance', 'Student');
        $this->attachStudentToClass($class->id, $student->id);

        $create = $this->postJson('/api/preschool/attendance', [
            'class_id' => $class->id,
            'student_id' => $student->id,
            'attendance_date' => '2026-05-14',
            'status' => 'present',
            'note' => 'On time',
        ]);

        $create
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.attendance.status', 'present');

        $attendanceId = $create->json('data.attendance.id');

        $this->putJson('/api/preschool/attendance/'.$attendanceId, [
            'status' => 'late',
            'note' => 'Arrived late',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.attendance.status', 'late');

        $this->getJson('/api/preschool/attendance?page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_consecutive_absences_create_repeated_absence_communication_and_reuse_it(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_531', 'preschool.admin531@hfccf.org');
        Sanctum::actingAs($admin);

        app(PreschoolAttendanceConfigurationService::class)->updateSettings($this->attendanceSettingsPayload([
            'absence_alert_days' => 3,
        ]), $admin);

        $class = $this->createPreschoolClass('PS-CLASS-531', 'Absence Alert Class');
        $student = $this->createPreschoolStudent('PS-STU-531', 'Absence', 'Student');
        $guardian = $this->createGuardian('Guardian Absence', '+855 12 531 531', 'guardian.absence531@hfccf.org');
        $this->linkGuardianToStudent($student->id, $guardian->id, $admin->id);
        $this->attachStudentToClass($class->id, $student->id);

        foreach (['2026-05-11', '2026-05-12', '2026-05-13'] as $date) {
            $this->postJson('/api/preschool/attendance', [
                'class_id' => $class->id,
                'student_id' => $student->id,
                'attendance_date' => $date,
                'status' => 'absent',
                'note' => 'Absent for alert regression',
            ])
                ->assertCreated()
                ->assertJsonPath('success', true);
        }

        $this->assertDatabaseCount('preschool_guardian_communications', 1);
        $this->assertDatabaseHas('preschool_guardian_communications', [
            'student_id' => $student->id,
            'guardian_id' => $guardian->id,
            'source_type' => 'attendance',
            'communication_type' => 'repeated_absence',
            'status' => 'queued',
            'created_by' => $admin->id,
        ]);

        $this->postJson('/api/preschool/attendance', [
            'class_id' => $class->id,
            'student_id' => $student->id,
            'attendance_date' => '2026-05-14',
            'status' => 'absent',
            'note' => 'Follow-up absence',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('preschool_guardian_communications', 1);
    }

    public function test_threshold_setting_controls_when_repeated_absence_alert_triggers(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_532', 'preschool.admin532@hfccf.org');
        Sanctum::actingAs($admin);

        app(PreschoolAttendanceConfigurationService::class)->updateSettings($this->attendanceSettingsPayload([
            'absence_alert_days' => 2,
        ]), $admin);

        $class = $this->createPreschoolClass('PS-CLASS-532', 'Threshold Alert Class');
        $student = $this->createPreschoolStudent('PS-STU-532', 'Threshold', 'Student');
        $guardian = $this->createGuardian('Guardian Threshold', '+855 12 532 532', 'guardian.threshold532@hfccf.org');
        $this->linkGuardianToStudent($student->id, $guardian->id, $admin->id);
        $this->attachStudentToClass($class->id, $student->id);

        foreach (['2026-05-11', '2026-05-12'] as $date) {
            $this->postJson('/api/preschool/attendance', [
                'class_id' => $class->id,
                'student_id' => $student->id,
                'attendance_date' => $date,
                'status' => 'absent',
                'note' => 'Threshold regression',
            ])
                ->assertCreated()
                ->assertJsonPath('success', true);
        }

        $this->assertDatabaseCount('preschool_guardian_communications', 1);
        $this->assertDatabaseHas('preschool_guardian_communications', [
            'student_id' => $student->id,
            'guardian_id' => $guardian->id,
            'communication_type' => 'repeated_absence',
            'created_by' => $admin->id,
        ]);
    }

    public function test_threshold_three_does_not_trigger_after_two_absences(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_533', 'preschool.admin533@hfccf.org');
        Sanctum::actingAs($admin);

        app(PreschoolAttendanceConfigurationService::class)->updateSettings($this->attendanceSettingsPayload([
            'absence_alert_days' => 3,
        ]), $admin);

        $class = $this->createPreschoolClass('PS-CLASS-533', 'No Trigger Class');
        $student = $this->createPreschoolStudent('PS-STU-533', 'No', 'Trigger');
        $guardian = $this->createGuardian('Guardian No Trigger', '+855 12 533 533', 'guardian.notrigger533@hfccf.org');
        $this->linkGuardianToStudent($student->id, $guardian->id, $admin->id);
        $this->attachStudentToClass($class->id, $student->id);

        foreach (['2026-05-11', '2026-05-12'] as $date) {
            $this->postJson('/api/preschool/attendance', [
                'class_id' => $class->id,
                'student_id' => $student->id,
                'attendance_date' => $date,
                'status' => 'absent',
                'note' => 'Below threshold',
            ])
                ->assertCreated()
                ->assertJsonPath('success', true);
        }

        $this->assertDatabaseCount('preschool_guardian_communications', 0);
    }

    public function test_attendance_save_still_succeeds_when_follow_up_sync_fails(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_534', 'preschool.admin534@hfccf.org');
        Sanctum::actingAs($admin);

        $this->app->instance(PreschoolGuardianCommunicationService::class, new class
        {
            public function syncAttendanceFollowUp(...$arguments): void
            {
                throw new \RuntimeException('forced follow-up failure');
            }
        });

        $class = $this->createPreschoolClass('PS-CLASS-534', 'Side Effect Safety Class');
        $student = $this->createPreschoolStudent('PS-STU-534', 'Side', 'Effect');
        $this->attachStudentToClass($class->id, $student->id);

        $response = $this->postJson('/api/preschool/attendance', [
            'class_id' => $class->id,
            'student_id' => $student->id,
            'attendance_date' => '2026-05-15',
            'status' => 'present',
            'note' => 'Follow-up failure should not break attendance save',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('preschool_attendance_records', [
            'class_id' => $class->id,
            'student_id' => $student->id,
            'attendance_date' => '2026-05-15 00:00:00',
            'status' => 'present',
        ]);
    }

    public function test_teacher_preschool_can_access_own_students_and_classes(): void
    {
        $teacher = $this->makeUserWithRole('teacher-preschool', 'usr_540', 'teacher.preschool540@hfccf.org');
        Sanctum::actingAs($teacher);

        $class = $this->createPreschoolClass('PS-CLASS-540', 'Teacher Class', $teacher->id, trim($teacher->first_name.' '.$teacher->last_name));
        $student = $this->createPreschoolStudent('PS-STU-540', 'Teacher', 'Student');
        DB::table('preschool_class_students')->insert([
            'class_id' => $class->id,
            'student_id' => $student->id,
            'enrolled_at' => now(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/api/preschool/teacher/my-classes')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment([
                'code' => 'PS-CLASS-540',
            ]);

        $this->getJson('/api/preschool/teacher/my-students')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment([
                'studentCode' => 'PS-STU-540',
            ]);

        $this->getJson('/api/preschool/teacher/attendance')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_teacher_preschool_cannot_manage_all_preschool_data(): void
    {
        $teacher = $this->makeUserWithRole('teacher-preschool', 'usr_550', 'teacher.preschool550@hfccf.org');
        Sanctum::actingAs($teacher);

        $this->postJson('/api/preschool/classes', [
            'code' => 'PS-CLASS-550',
            'name' => 'Forbidden Class',
            'level' => 'Nursery',
            'status' => 'active',
        ])->assertForbidden();

        $this->postJson('/api/preschool/students', [
            'first_name' => 'Forbidden',
            'last_name' => 'Student',
            'status' => 'active',
        ])->assertForbidden();

        $this->postJson('/api/preschool/payments', [
            'student_id' => 1,
            'class_id' => 1,
            'amount' => 1,
            'payment_method' => 'cash',
            'payment_status' => 'pending',
        ])->assertForbidden();
    }

    public function test_superadmin_can_access_preschool_endpoints(): void
    {
        $superadmin = $this->makeUserWithRole('superadmin', 'usr_560', 'superadmin560@hfccf.org');
        Sanctum::actingAs($superadmin);

        $this->getJson('/api/preschool/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson('/api/preschool/classes')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson('/api/preschool/students')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson('/api/preschool/payments')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_unauthorized_users_are_blocked_from_preschool_endpoints(): void
    {
        $this->getJson('/api/preschool/dashboard')
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
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

        $this->syncPermissions($user, $role);

        return $user;
    }

    private function syncPermissions(User $user, Role $role): void
    {
        $rows = $role->permissions->map(static fn ($permission) => [
            'user_id' => $user->id,
            'permission_code' => $permission->code,
        ])->all();

        if ($rows !== []) {
            DB::table('user_permissions')->insert($rows);
        }
    }

    private function createPreschoolClass(string $code, string $name, ?string $teacherId = null, ?string $teacherDisplayName = null): object
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

    private function createPreschoolStudent(string $code, string $firstName, string $lastName): object
    {
        $studentId = DB::table('preschool_students')->insertGetId([
            'student_code' => $code,
            'first_name' => $firstName,
            'last_name' => $lastName,
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

    private function attachStudentToClass(int $classId, int $studentId): void
    {
        DB::table('preschool_class_students')->insert([
            'class_id' => $classId,
            'student_id' => $studentId,
            'enrolled_at' => now(),
            'status' => 'active',
            'enrollment_status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
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

    private function linkGuardianToStudent(int $studentId, int $guardianId, string $userId): PreschoolStudentGuardian
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
}

