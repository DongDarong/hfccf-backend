<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\PreschoolAssessmentStatus;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreschoolAssessmentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_category_list_is_available_for_preschool_staff(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psa-100', 'preschool.assessment100@hfccf.org');
        Sanctum::actingAs($admin);

        $this->getJson('/api/preschool/assessment-categories')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['code' => 'learning_progress'])
            ->assertJsonFragment(['code' => 'teacher_comment']);
    }

    public function test_admin_can_create_and_finalize_assessment(): void
    {
        $admin = $this->makeUserWithRole('adminpreschool', 'psa-110', 'preschool.assessment110@hfccf.org');
        Sanctum::actingAs($admin);

        $student = $this->createPreschoolStudent('PS-ASSESS-110', 'Lina', 'Chan');
        $class = $this->createPreschoolClass('PS-CLASS-ASSESS-110', 'Assessment Class');
        $this->attachStudentToClass($class->id, $student->id);
        $categoryId = $this->categoryId('learning_progress');

        $create = $this->postJson("/api/preschool/students/{$student->id}/assessments", [
            'class_id' => $class->id,
            'category_id' => $categoryId,
            'period_label' => 'Term 1',
            'assessment_date' => '2026-05-14',
            'score' => 88,
            'rating' => 'proficient',
            'observation' => 'Strong engagement in guided tasks.',
            'teacher_comment' => 'Keep supporting fine motor work.',
        ]);

        $create
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.assessment.status', PreschoolAssessmentStatus::DRAFT);

        $assessmentId = $create->json('data.assessment.id');

        $this->putJson("/api/preschool/assessments/{$assessmentId}", [
            'period_label' => 'Term 1 - Updated',
            'score' => 90,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.assessment.periodLabel', 'Term 1 - Updated');

        $this->postJson("/api/preschool/assessments/{$assessmentId}/finalize")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.assessment.status', PreschoolAssessmentStatus::FINALIZED);

        $this->putJson("/api/preschool/assessments/{$assessmentId}", [
            'score' => 95,
        ])->assertStatus(409);

        $this->getJson("/api/preschool/students/{$student->id}/progress-summary")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.finalizedAssessments', 1)
            ->assertJsonPath('data.summary.averageScore', 90);
    }

    public function test_teacher_can_manage_assessments_for_assigned_student_only(): void
    {
        $teacher = $this->makeUserWithRole('teacher-preschool', 'psa-120', 'preschool.teacher120@hfccf.org');
        Sanctum::actingAs($teacher);

        $ownClass = $this->createPreschoolClass('PS-CLASS-ASSESS-120', 'Teacher Class', $teacher->id, trim($teacher->first_name.' '.$teacher->last_name));
        $otherTeacher = $this->makeUserWithRole('teacher-preschool', 'psa-121', 'preschool.teacher121@hfccf.org');
        $otherClass = $this->createPreschoolClass('PS-CLASS-ASSESS-121', 'Other Teacher Class', $otherTeacher->id, trim($otherTeacher->first_name.' '.$otherTeacher->last_name));

        $student = $this->createPreschoolStudent('PS-ASSESS-120', 'Mina', 'Khan');
        $this->attachStudentToClass($ownClass->id, $student->id);
        $categoryId = $this->categoryId('behavior');

        $create = $this->postJson("/api/preschool/students/{$student->id}/assessments", [
            'class_id' => $ownClass->id,
            'category_id' => $categoryId,
            'period_label' => 'Week 1',
            'assessment_date' => '2026-05-15',
            'rating' => 'developing',
            'observation' => 'Improving classroom transitions.',
        ]);

        $create
            ->assertCreated()
            ->assertJsonPath('success', true);

        $assessmentId = $create->json('data.assessment.id');

        $this->getJson("/api/preschool/students/{$student->id}/assessments")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['periodLabel' => 'Week 1']);

        $otherStudent = $this->createPreschoolStudent('PS-ASSESS-121', 'Other', 'Student');
        $this->attachStudentToClass($otherClass->id, $otherStudent->id);

        $this->postJson("/api/preschool/students/{$otherStudent->id}/assessments", [
            'class_id' => $otherClass->id,
            'category_id' => $categoryId,
            'period_label' => 'Forbidden',
            'assessment_date' => '2026-05-15',
        ])->assertForbidden();

        $this->postJson("/api/preschool/assessments/{$assessmentId}/archive")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.assessment.status', PreschoolAssessmentStatus::ARCHIVED);
    }

    public function test_unauthorized_users_are_blocked_from_preschool_assessment_endpoints(): void
    {
        $this->getJson('/api/preschool/assessment-categories')
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
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function categoryId(string $code): int
    {
        return (int) DB::table('preschool_assessment_categories')
            ->where('code', $code)
            ->value('id');
    }
}
