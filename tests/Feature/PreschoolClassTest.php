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

    private function classLevelId(string $code): int
    {
        return (int) DB::table('preschool_class_levels')->where('code', $code)->value('id');
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
}
