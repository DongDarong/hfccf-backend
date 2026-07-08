<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolClassTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_adminpreschool_class_create_generates_code_from_selected_level(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_cls_600', 'preschool.class600@hfccf.org');
        Sanctum::actingAs($admin);

        $nurseryLevelId = $this->classLevelId('NUR');
        $teacher = $this->makeUserWithRole('teacher-preschool', 'usr_cls_601', 'teacher.class601@hfccf.org');

        $create = $this->postJson('/api/preschool/classes', [
            'name' => 'Morning Stars',
            'teacher_user_id' => $teacher->id,
            'teacher_display_name' => trim($teacher->first_name.' '.$teacher->last_name),
            'class_level_id' => $nurseryLevelId,
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
            ->assertJsonPath('data.class.classLevelId', (int) $nurseryLevelId)
            ->assertJsonPath('data.class.level', 'Nursery');

        $classId = $create->json('data.class.id');

        $this->assertDatabaseHas('preschool_classes', [
            'id' => $classId,
            'class_level_id' => $nurseryLevelId,
            'code' => 'PS-NUR-001',
            'level' => 'Nursery',
        ]);

        $secondCreate = $this->postJson('/api/preschool/classes', [
            'name' => 'Morning Stars Two',
            'teacher_user_id' => $teacher->id,
            'teacher_display_name' => trim($teacher->first_name.' '.$teacher->last_name),
            'class_level_id' => $nurseryLevelId,
            'schedule' => 'Mon-Fri 8:00 AM',
            'students_count' => 0,
            'status' => 'active',
            'room' => 'Room A2',
            'notes' => 'Created by test',
        ]);

        $secondCreate
            ->assertCreated()
            ->assertJsonPath('data.class.code', 'PS-NUR-002')
            ->assertJsonPath('data.class.classLevelId', (int) $nurseryLevelId);
    }

    public function test_legacy_level_string_is_mapped_to_class_level_id(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_cls_610', 'preschool.class610@hfccf.org');
        Sanctum::actingAs($admin);

        $prepLevelId = $this->classLevelId('PRE');

        $create = $this->postJson('/api/preschool/classes', [
            'name' => 'Prep Class',
            'level' => 'Prep',
            'schedule' => 'Mon-Fri 8:00 AM',
            'students_count' => 0,
            'status' => 'active',
        ]);

        $create
            ->assertCreated()
            ->assertJsonPath('data.class.classLevelId', (int) $prepLevelId)
            ->assertJsonPath('data.class.code', 'PS-PRE-001');

        $classId = $create->json('data.class.id');

        $this->assertDatabaseHas('preschool_classes', [
            'id' => $classId,
            'class_level_id' => $prepLevelId,
            'level' => 'Prep',
            'code' => 'PS-PRE-001',
        ]);
    }

    public function test_inactive_class_level_cannot_be_used_for_create(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_cls_620', 'preschool.class620@hfccf.org');
        Sanctum::actingAs($admin);

        $prepLevelId = $this->classLevelId('PRE');
        DB::table('preschool_class_levels')->where('id', $prepLevelId)->update([
            'is_active' => 0,
            'updated_at' => now(),
        ]);

        $this->postJson('/api/preschool/classes', [
            'name' => 'Inactive Level Class',
            'class_level_id' => $prepLevelId,
            'schedule' => 'Mon-Fri 8:00 AM',
            'students_count' => 0,
            'status' => 'active',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('preschool_classes', [
            'name' => 'Inactive Level Class',
        ]);
    }

    public function test_teacher_assignment_must_reference_an_active_preschool_teacher(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_cls_630', 'preschool.class630@hfccf.org');
        Sanctum::actingAs($admin);

        $teacher = $this->makeUserWithRole('teacher-preschool', 'usr_cls_631', 'teacher.class631@hfccf.org');
        $teacherRole = Role::query()->with('permissions')->findOrFail('teacher-preschool');
        $inactiveTeacher = User::query()->create([
            'id' => 'usr_cls_632',
            'first_name' => 'Inactive',
            'last_name' => 'Teacher',
            'username' => 'Inactive Teacher Unique',
            'email' => 'teacher.class632@hfccf.org',
            'phone' => '+855 12 555 556',
            'role_code' => $teacherRole->code,
            'department_code' => $teacherRole->department_code,
            'status' => 'inactive',
            'password' => 'secret-pass',
        ]);
        $this->syncPermissions($inactiveTeacher, $teacherRole);
        DB::table('users')->where('id', $inactiveTeacher->id)->update([
            'status' => 'inactive',
            'updated_at' => now(),
        ]);

        $this->postJson('/api/preschool/classes', [
            'name' => 'Active Teacher Class',
            'teacher_user_id' => $teacher->id,
            'class_level_id' => $this->classLevelId('NUR'),
            'schedule' => 'Mon-Fri 8:00 AM',
            'students_count' => 0,
            'status' => 'active',
        ])->assertCreated();

        $this->postJson('/api/preschool/classes', [
            'name' => 'Inactive Teacher Class',
            'teacher_user_id' => $inactiveTeacher->id,
            'class_level_id' => $this->classLevelId('NUR'),
            'schedule' => 'Mon-Fri 8:00 AM',
            'students_count' => 0,
            'status' => 'active',
        ])->assertStatus(422);

        $this->postJson('/api/preschool/classes', [
            'name' => 'Wrong Role Class',
            'teacher_user_id' => $admin->id,
            'class_level_id' => $this->classLevelId('NUR'),
            'schedule' => 'Mon-Fri 8:00 AM',
            'students_count' => 0,
            'status' => 'active',
        ])->assertStatus(422);
    }

    public function test_adminpreschool_class_update_syncs_student_assignments_without_500(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_cls_640', 'preschool.class640@hfccf.org');
        Sanctum::actingAs($admin);

        $teacher = $this->makeUserWithRole('teacher-preschool', 'usr_cls_641', 'teacher.class641@hfccf.org');
        $class = $this->createPreschoolClass('PS-CLS-640', 'Sync Class');
        $firstStudent = $this->createPreschoolStudent('PS-STU-640', 'Mey', 'Sok');
        $secondStudent = $this->createPreschoolStudent('PS-STU-641', 'Sothea', 'Nim');

        $this->attachStudentToClass((int) $class->id, (int) $firstStudent->id);
        $this->attachStudentToClass((int) $class->id, (int) $secondStudent->id);

        $update = $this->putJson('/api/preschool/classes/'.$class->id, [
            'name' => 'Sync Class Updated',
            'teacher_user_id' => $teacher->id,
            'teacher_display_name' => trim($teacher->first_name.' '.$teacher->last_name),
            'class_level_id' => $this->classLevelId('NUR'),
            'schedule' => 'Mon-Fri 8:00 AM',
            'students_count' => 2,
            'status' => 'pending',
            'room' => 'Room B2',
            'notes' => 'Updated through regression test',
            'student_ids' => [(int) $firstStudent->id, (int) $secondStudent->id],
        ]);

        $update
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.class.name', 'Sync Class Updated')
            ->assertJsonPath('data.class.teacherUserId', $teacher->id)
            ->assertJsonPath('data.class.studentsCount', 2)
            ->assertJsonPath('data.class.status', 'pending');

        $this->assertDatabaseHas('preschool_classes', [
            'id' => $class->id,
            'name' => 'Sync Class Updated',
            'teacher_user_id' => $teacher->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('preschool_class_students', [
            'class_id' => $class->id,
            'student_id' => $firstStudent->id,
            'status' => 'active',
            'enrollment_status' => 'active',
        ]);

        $this->assertDatabaseHas('preschool_class_students', [
            'class_id' => $class->id,
            'student_id' => $secondStudent->id,
            'status' => 'active',
            'enrollment_status' => 'active',
        ]);
    }

    public function test_adminpreschool_class_update_rejects_invalid_teacher_assignment_with_422(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_cls_642', 'preschool.class642@hfccf.org');
        Sanctum::actingAs($admin);

        $teacherRole = Role::query()->with('permissions')->findOrFail('teacher-preschool');
        $inactiveTeacher = User::query()->create([
            'id' => 'usr_cls_643',
            'first_name' => 'Inactive',
            'last_name' => 'Teacher',
            'username' => 'Inactive Teacher Regression',
            'email' => 'teacher.class643@hfccf.org',
            'phone' => '+855 12 555 643',
            'role_code' => $teacherRole->code,
            'department_code' => $teacherRole->department_code,
            'status' => 'inactive',
            'password' => 'secret-pass',
        ]);
        $this->syncPermissions($inactiveTeacher, $teacherRole);

        $class = $this->createPreschoolClass('PS-CLS-642', 'Validation Class');

        $this->putJson('/api/preschool/classes/'.$class->id, [
            'name' => 'Validation Class Updated',
            'teacher_user_id' => $inactiveTeacher->id,
            'class_level_id' => $this->classLevelId('NUR'),
            'schedule' => 'Mon-Fri 8:00 AM',
            'students_count' => 0,
            'status' => 'active',
        ])->assertStatus(422);

        $this->assertDatabaseHas('preschool_classes', [
            'id' => $class->id,
            'name' => 'Validation Class',
            'teacher_user_id' => null,
        ]);
    }

    public function test_adminpreschool_class_update_can_clear_teacher_assignment(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'usr_cls_644', 'preschool.class644@hfccf.org');
        Sanctum::actingAs($admin);

        $teacher = $this->makeUserWithRole('teacher-preschool', 'usr_cls_645', 'teacher.class645@hfccf.org');
        $class = $this->createPreschoolClass('PS-CLS-644', 'Clear Teacher Class', $teacher->id, trim($teacher->first_name.' '.$teacher->last_name));

        $this->putJson('/api/preschool/classes/'.$class->id, [
            'name' => 'Clear Teacher Class',
            'teacher_user_id' => null,
            'teacher_display_name' => null,
            'class_level_id' => $this->classLevelId('NUR'),
            'schedule' => 'Mon-Fri 8:00 AM',
            'students_count' => 0,
            'status' => 'active',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.class.teacherUserId', null)
            ->assertJsonPath('data.class.teacherDisplayName', null);

        $this->assertDatabaseHas('preschool_classes', [
            'id' => $class->id,
            'teacher_user_id' => null,
            'teacher_display_name' => null,
        ]);
    }

    private function classLevelId(string $code): int
    {
        return (int) DB::table('preschool_class_levels')->where('code', $code)->value('id');
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
