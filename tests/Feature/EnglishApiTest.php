<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EnglishApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_adminenglish_can_manage_classes(): void
    {
        $admin = $this->makeUserWithRole('adminenglish', 'usr_810', 'english.admin810@hfccf.org');
        Sanctum::actingAs($admin);

        $teacher = $this->makeUserWithRole('teacher-english', 'usr_811', 'english.teacher811@hfccf.org');
        $student = $this->createEnglishStudent('ENG-STU-810', 'Alpha', 'One');

        $create = $this->postJson('/api/english/classes', [
            'class_code' => 'ENG-CLS-810',
            'name' => 'English A',
            'level' => 'Beginner',
            'teacher_user_id' => $teacher->id,
            'schedule' => 'Mon-Fri 8:00 AM',
            'room' => 'Room E1',
            'status' => 'active',
            'description' => 'English class test',
            'student_ids' => [$student->id],
        ]);

        $create->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.class.classCode', 'ENG-CLS-810')
            ->assertJsonPath('data.class.teacherUserId', $teacher->id);

        $classId = $create->json('data.class.id');

        $this->assertDatabaseHas('english_classes', [
            'id' => $classId,
            'class_code' => 'ENG-CLS-810',
            'teacher_user_id' => $teacher->id,
        ]);

        $this->assertDatabaseHas('english_class_students', [
            'class_id' => $classId,
            'student_id' => $student->id,
            'status' => 'active',
        ]);

        $this->putJson('/api/english/classes/'.$classId, [
            'name' => 'English A Updated',
            'status' => 'inactive',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.class.name', 'English A Updated')
            ->assertJsonPath('data.class.status', 'inactive');

        $this->deleteJson('/api/english/classes/'.$classId)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('english_classes', [
            'id' => $classId,
        ]);
    }

    public function test_adminenglish_can_manage_students(): void
    {
        $admin = $this->makeUserWithRole('adminenglish', 'usr_820', 'english.admin820@hfccf.org');
        Sanctum::actingAs($admin);

        $class = $this->createEnglishClass('ENG-CLS-820', 'Student Class');

        $create = $this->postJson('/api/english/students', [
            'student_code' => 'ENG-STU-820',
            'first_name' => 'Nita',
            'last_name' => 'Sok',
            'gender' => 'female',
            'date_of_birth' => '2010-02-10',
            'guardian_name' => 'Sok Dara',
            'guardian_phone' => '+855 12 820 820',
            'email' => 'student820@hfccf.org',
            'phone' => '+855 12 821 821',
            'address' => 'Phnom Penh',
            'status' => 'active',
            'class_ids' => [$class->id],
        ]);

        $create->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.student.studentCode', 'ENG-STU-820');

        $studentId = $create->json('data.student.id');

        $this->assertDatabaseHas('english_students', [
            'id' => $studentId,
            'student_code' => 'ENG-STU-820',
        ]);

        $this->assertDatabaseHas('english_class_students', [
            'class_id' => $class->id,
            'student_id' => $studentId,
        ]);

        $this->putJson('/api/english/students/'.$studentId, [
            'guardian_phone' => '+855 12 822 822',
            'status' => 'inactive',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.student.status', 'inactive');

        $this->deleteJson('/api/english/students/'.$studentId)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('english_students', [
            'id' => $studentId,
        ]);
    }

    public function test_adminenglish_can_manage_tasks(): void
    {
        $admin = $this->makeUserWithRole('adminenglish', 'usr_830', 'english.admin830@hfccf.org');
        Sanctum::actingAs($admin);

        $class = $this->createEnglishClass('ENG-CLS-830', 'Task Class');

        $create = $this->postJson('/api/english/tasks', [
            'class_id' => $class->id,
            'title' => 'Vocabulary task',
            'description' => 'Write ten words',
            'due_date' => '2026-05-20',
            'task_status' => 'assigned',
        ]);

        $create->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.task.title', 'Vocabulary task')
            ->assertJsonPath('data.task.taskStatus', 'assigned');

        $taskId = $create->json('data.task.id');

        $this->assertDatabaseHas('english_tasks', [
            'id' => $taskId,
            'title' => 'Vocabulary task',
            'assigned_by_user_id' => $admin->id,
        ]);

        $this->putJson('/api/english/tasks/'.$taskId, [
            'task_status' => 'completed',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.task.taskStatus', 'completed');

        $this->deleteJson('/api/english/tasks/'.$taskId)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('english_tasks', [
            'id' => $taskId,
        ]);
    }

    public function test_teacher_english_can_access_only_own_classes_and_tasks(): void
    {
        $teacher = $this->makeUserWithRole('teacher-english', 'usr_840', 'english.teacher840@hfccf.org');
        Sanctum::actingAs($teacher);

        $ownClass = $this->createEnglishClass('ENG-CLS-840', 'Teacher Class', $teacher->id);
        $otherTeacher = $this->makeUserWithRole('teacher-english', 'usr_841', 'english.teacher841@hfccf.org');
        $otherClass = $this->createEnglishClass('ENG-CLS-841', 'Other Class', $otherTeacher->id);
        $this->createEnglishTask($ownClass->id, $teacher->id, 'Teacher task');
        $this->createEnglishTask($otherClass->id, $otherTeacher->id, 'Other task');

        $this->getJson('/api/english/teacher/classes')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['classCode' => 'ENG-CLS-840'])
            ->assertJsonMissing(['classCode' => 'ENG-CLS-841']);

        $this->getJson('/api/english/teacher/tasks')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['title' => 'Teacher task'])
            ->assertJsonMissing(['title' => 'Other task']);

        $this->postJson('/api/english/tasks', [
            'class_id' => $otherClass->id,
            'title' => 'Forbidden task',
            'task_status' => 'assigned',
        ])->assertForbidden();
    }

    public function test_teacher_english_can_review_own_class_submissions(): void
    {
        $teacher = $this->makeUserWithRole('teacher-english', 'usr_850', 'english.teacher850@hfccf.org');
        Sanctum::actingAs($teacher);

        $class = $this->createEnglishClass('ENG-CLS-850', 'Review Class', $teacher->id);
        $student = $this->createEnglishStudent('ENG-STU-850', 'Review', 'Student');
        $this->attachStudentToClass($class->id, $student->id);
        $task = $this->createEnglishTask($class->id, $teacher->id, 'Review task');
        $submission = $this->createEnglishSubmission($task->id, $student->id);

        $this->putJson('/api/english/submissions/'.$submission->id, [
            'submission_status' => 'reviewed',
            'score' => 96,
            'feedback' => 'Excellent work.',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.submission.submissionStatus', 'reviewed')
            ->assertJsonPath('data.submission.score', '96.00');

        $this->assertDatabaseHas('english_task_submissions', [
            'id' => $submission->id,
            'reviewed_by_user_id' => $teacher->id,
            'submission_status' => 'reviewed',
        ]);

        $otherTeacher = $this->makeUserWithRole('teacher-english', 'usr_851', 'english.teacher851@hfccf.org');
        $otherClass = $this->createEnglishClass('ENG-CLS-851', 'Other Review Class', $otherTeacher->id);
        $otherTask = $this->createEnglishTask($otherClass->id, $otherTeacher->id, 'Other review task');
        $otherSubmission = $this->createEnglishSubmission($otherTask->id, $student->id);

        $this->putJson('/api/english/submissions/'.$otherSubmission->id, [
            'submission_status' => 'reviewed',
            'score' => 50,
        ])->assertForbidden();
    }

    public function test_unauthorized_users_are_blocked_from_english_endpoints(): void
    {
        $this->getJson('/api/english/dashboard')
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    private function makeUserWithRole(string $roleCode, string $id, string $email): User
    {
        $role = Role::query()->with('permissions')->findOrFail($roleCode);
        $username = $roleCode.'-'.$id;

        $user = User::query()->create([
            'id' => $id,
            'first_name' => ucfirst(str_replace('-', ' ', $roleCode)),
            'last_name' => 'User',
            'username' => $username,
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

    private function createEnglishClass(string $code, string $name, ?string $teacherId = null): object
    {
        $classId = DB::table('english_classes')->insertGetId([
            'class_code' => $code,
            'name' => $name,
            'level' => 'Beginner',
            'teacher_user_id' => $teacherId,
            'schedule' => 'Mon-Fri 8:00 AM',
            'room' => 'Room E1',
            'status' => 'active',
            'description' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('english_classes')->where('id', $classId)->first();
    }

    private function createEnglishStudent(string $code, string $firstName, string $lastName): object
    {
        $studentId = DB::table('english_students')->insertGetId([
            'student_code' => $code,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'gender' => 'female',
            'date_of_birth' => '2010-01-01',
            'guardian_name' => 'Guardian',
            'guardian_phone' => '+855 12 000 000',
            'email' => null,
            'phone' => '+855 12 111 111',
            'address' => 'Phnom Penh',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('english_students')->where('id', $studentId)->first();
    }

    private function attachStudentToClass(int $classId, int $studentId): void
    {
        DB::table('english_class_students')->insert([
            'class_id' => $classId,
            'student_id' => $studentId,
            'enrolled_at' => now(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createEnglishTask(int $classId, string $teacherId, string $title): object
    {
        $taskId = DB::table('english_tasks')->insertGetId([
            'class_id' => $classId,
            'assigned_by_user_id' => $teacherId,
            'title' => $title,
            'description' => null,
            'due_date' => '2026-05-20',
            'task_status' => 'assigned',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('english_tasks')->where('id', $taskId)->first();
    }

    private function createEnglishSubmission(int $taskId, int $studentId): object
    {
        $submissionId = DB::table('english_task_submissions')->insertGetId([
            'task_id' => $taskId,
            'student_id' => $studentId,
            'submission_text' => 'Initial submission',
            'submitted_at' => now(),
            'submission_status' => 'submitted',
            'score' => null,
            'feedback' => null,
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('english_task_submissions')->where('id', $submissionId)->first();
    }
}
