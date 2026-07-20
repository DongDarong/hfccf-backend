<?php

namespace Tests\Feature\Preschool;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolTeacherMyClassDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_teacher_can_view_assigned_class(): void
    {
        $teacher = $this->makeUserWithRole('teacher-preschool', 'usr_720', 'teacher.preschool720@hfccf.org');
        Sanctum::actingAs($teacher);

        $class = $this->createPreschoolClass('PS-CLASS-720', 'Assigned Detail Class', $teacher->id);

        $this->getJson('/api/preschool/teacher/my-classes/'.$class->id)
            ->assertOk()
            ->assertJsonPath('data.class.id', $class->id)
            ->assertJsonPath('data.class.code', 'PS-CLASS-720')
            ->assertJsonPath('data.class.name', 'Assigned Detail Class');
    }

    public function test_teacher_cannot_view_another_teachers_class(): void
    {
        $teacher = $this->makeUserWithRole('teacher-preschool', 'usr_721', 'teacher.preschool721@hfccf.org');
        Sanctum::actingAs($teacher);

        // Class assigned to nobody (foreign class)
        $foreignClass = $this->createPreschoolClass('PS-CLASS-722', 'Foreign Detail Class');

        // Scoped query returns null for unassigned class -> 404 (does not leak existence)
        $this->getJson('/api/preschool/teacher/my-classes/'.$foreignClass->id)
            ->assertNotFound();
    }

    public function test_unrelated_role_is_denied(): void
    {
        $coach = $this->makeUserWithRole('coach', 'usr_723', 'coach723@hfccf.org');
        Sanctum::actingAs($coach);

        $class = $this->createPreschoolClass('PS-CLASS-723', 'Denied Role Class');

        $this->getJson('/api/preschool/teacher/my-classes/'.$class->id)
            ->assertForbidden();
    }

    public function test_response_contains_correct_active_student_count(): void
    {
        $teacher = $this->makeUserWithRole('teacher-preschool', 'usr_724', 'teacher.preschool724@hfccf.org');
        Sanctum::actingAs($teacher);

        $class = $this->createPreschoolClass('PS-CLASS-724', 'Counting Class', $teacher->id);

        $activeOne = $this->createPreschoolStudent('PS-STU-724A', 'Active', 'One');
        $activeTwo = $this->createPreschoolStudent('PS-STU-724B', 'Active', 'Two');
        $inactive = $this->createPreschoolStudent('PS-STU-724C', 'Inactive', 'Three');

        $this->attachStudentToClass($class->id, $activeOne->id, 'active');
        $this->attachStudentToClass($class->id, $activeTwo->id, 'active');
        $this->attachStudentToClass($class->id, $inactive->id, 'inactive');

        $this->getJson('/api/preschool/teacher/my-classes/'.$class->id)
            ->assertOk()
            ->assertJsonPath('data.class.studentsCount', 2);
    }

    public function test_response_contains_only_authorized_active_students(): void
    {
        $teacher = $this->makeUserWithRole('teacher-preschool', 'usr_725', 'teacher.preschool725@hfccf.org');
        Sanctum::actingAs($teacher);

        $class = $this->createPreschoolClass('PS-CLASS-725', 'Roster Class', $teacher->id);
        $enrolled = $this->createPreschoolStudent('PS-STU-725A', 'Enrolled', 'Student');
        $withdrawn = $this->createPreschoolStudent('PS-STU-725B', 'Withdrawn', 'Student');

        $this->attachStudentToClass($class->id, $enrolled->id, 'active');
        $this->attachStudentToClass($class->id, $withdrawn->id, 'inactive');

        $response = $this->getJson('/api/preschool/teacher/my-classes/'.$class->id)
            ->assertOk();

        // Active roster contains only the active student
        $response->assertJsonPath('data.class.activeStudentAssignments.0.studentCode', 'PS-STU-725A');

        $activeAssignments = $response->json('data.class.activeStudentAssignments');
        $this->assertCount(1, $activeAssignments);
    }

    public function test_no_n_plus_one_regression_on_detail_query(): void
    {
        $teacher = $this->makeUserWithRole('teacher-preschool', 'usr_726', 'teacher.preschool726@hfccf.org');
        Sanctum::actingAs($teacher);

        $class = $this->createPreschoolClass('PS-CLASS-726', 'Perf Class', $teacher->id);
        for ($i = 0; $i < 5; $i++) {
            $student = $this->createPreschoolStudent('PS-STU-726-'.$i, 'Student', (string) $i);
            $this->attachStudentToClass($class->id, $student->id, 'active');
        }

        DB::enableQueryLog();
        $this->getJson('/api/preschool/teacher/my-classes/'.$class->id)->assertOk();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Eager-loaded relations keep the query count bounded regardless of student volume.
        $this->assertLessThan(15, $queryCount, 'Detail endpoint should not trigger N+1 queries.');
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

    private function createPreschoolClass(string $code, string $name, ?string $teacherId = null, int $studentsCount = 0): object
    {
        $classId = DB::table('preschool_classes')->insertGetId([
            'code' => $code,
            'name' => $name,
            'teacher_user_id' => $teacherId,
            'teacher_display_name' => $teacherId ? 'Teacher User' : null,
            'level' => 'Nursery',
            'schedule' => 'Mon-Fri 8:00 AM',
            'students_count' => $studentsCount,
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

    private function attachStudentToClass(int $classId, int $studentId, string $status = 'active'): void
    {
        DB::table('preschool_class_students')->insert([
            'class_id' => $classId,
            'student_id' => $studentId,
            'enrolled_at' => now(),
            'status' => $status,
            'enrollment_status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
