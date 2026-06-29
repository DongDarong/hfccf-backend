<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
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
}

