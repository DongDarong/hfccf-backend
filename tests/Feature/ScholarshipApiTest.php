<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScholarshipApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_adminscholarship_can_manage_students(): void
    {
        $admin = $this->makeUserWithRole('adminscholarship', 'usr_700', 'scholarship.admin700@hfccf.org');
        Sanctum::actingAs($admin);

        $create = $this->postJson('/api/scholarship/students', [
            'student_code' => 'SCH-STU-101',
            'first_name' => 'Sok',
            'last_name' => 'Dara',
            'gender' => 'male',
            'date_of_birth' => '2008-01-15',
            'phone' => '+855 12 101 101',
            'email' => 'student101@hfccf.org',
            'school_name' => 'HFCCF High School',
            'grade_level' => 'Grade 10',
            'guardian_name' => 'Sok Vannak',
            'guardian_phone' => '+855 12 111 111',
            'address' => 'Phnom Penh',
            'status' => 'active',
            'notes' => 'Created by scholarship test',
        ]);

        $create->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.student.studentCode', 'SCH-STU-101');

        $studentId = $create->json('data.student.id');

        $this->assertDatabaseHas('scholarship_students', [
            'id' => $studentId,
            'student_code' => 'SCH-STU-101',
        ]);

        $this->putJson('/api/scholarship/students/'.$studentId, [
            'grade_level' => 'Grade 11',
            'status' => 'pending',
        ])->assertOk()
            ->assertJsonPath('data.student.gradeLevel', 'Grade 11')
            ->assertJsonPath('data.student.status', 'pending');

        $this->deleteJson('/api/scholarship/students/'.$studentId)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('scholarship_students', [
            'id' => $studentId,
        ]);
    }

    public function test_adminscholarship_can_manage_applications(): void
    {
        $admin = $this->makeUserWithRole('adminscholarship', 'usr_710', 'scholarship.admin710@hfccf.org');
        Sanctum::actingAs($admin);

        $student = $this->createScholarshipStudent('SCH-STU-210', 'Meta', 'Oum');

        $create = $this->postJson('/api/scholarship/applications', [
            'student_id' => $student->id,
            'application_code' => 'SCH-APP-210',
            'scholarship_type' => 'Academic Excellence',
            'requested_amount' => 1200,
            'academic_year' => '2025-2026',
            'submission_date' => '2026-05-14',
            'application_status' => 'submitted',
            'notes' => 'Initial application',
        ]);

        $create->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.application.applicationCode', 'SCH-APP-210')
            ->assertJsonPath('data.application.applicationStatus', 'submitted');

        $applicationId = $create->json('data.application.id');

        $this->assertDatabaseHas('scholarship_applications', [
            'id' => $applicationId,
            'application_code' => 'SCH-APP-210',
            'application_status' => 'submitted',
        ]);

        $this->assertDatabaseHas('scholarship_status_histories', [
            'application_id' => $applicationId,
            'new_status' => 'submitted',
            'changed_by_user_id' => $admin->id,
        ]);

        $this->putJson('/api/scholarship/applications/'.$applicationId, [
            'application_status' => 'under_review',
            'notes' => 'Moved to review queue',
        ])->assertOk()
            ->assertJsonPath('data.application.applicationStatus', 'under_review');

        $this->deleteJson('/api/scholarship/applications/'.$applicationId)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('scholarship_applications', [
            'id' => $applicationId,
        ]);
    }

    public function test_adminscholarship_can_approve_and_reject_applications(): void
    {
        $admin = $this->makeUserWithRole('adminscholarship', 'usr_720', 'scholarship.admin720@hfccf.org');
        Sanctum::actingAs($admin);

        $student = $this->createScholarshipStudent('SCH-STU-220', 'Nita', 'Lida');

        $approveResponse = $this->postJson('/api/scholarship/applications', [
            'student_id' => $student->id,
            'scholarship_type' => 'Need Based',
            'requested_amount' => 800,
            'academic_year' => '2025-2026',
            'submission_date' => '2026-05-14',
            'application_status' => 'submitted',
        ]);

        $approveId = $approveResponse->json('data.application.id');

        $approvePatch = $this->patchJson('/api/scholarship/applications/'.$approveId.'/approve', [
            'note' => 'Approved after review',
        ])->assertOk()
            ->assertJsonPath('data.application.applicationStatus', 'approved');

        $this->assertNotNull($approvePatch->json('data.application.approvedAt'));

        $this->assertDatabaseHas('scholarship_status_histories', [
            'application_id' => $approveId,
            'new_status' => 'approved',
        ]);

        $rejectResponse = $this->postJson('/api/scholarship/applications', [
            'student_id' => $student->id,
            'scholarship_type' => 'Transport Support',
            'requested_amount' => 500,
            'academic_year' => '2025-2026',
            'submission_date' => '2026-05-14',
            'application_status' => 'submitted',
        ]);

        $rejectId = $rejectResponse->json('data.application.id');

        $rejectPatch = $this->patchJson('/api/scholarship/applications/'.$rejectId.'/reject', [
            'rejection_reason' => 'Incomplete documents',
            'note' => 'Rejected by admin',
        ])->assertOk()
            ->assertJsonPath('data.application.applicationStatus', 'rejected');

        $this->assertNotNull($rejectPatch->json('data.application.rejectedAt'));
    }

    public function test_adminscholarship_can_manage_reviews(): void
    {
        $admin = $this->makeUserWithRole('adminscholarship', 'usr_730', 'scholarship.admin730@hfccf.org');
        Sanctum::actingAs($admin);

        $student = $this->createScholarshipStudent('SCH-STU-230', 'Bora', 'Kim');
        $application = $this->createScholarshipApplication($student->id, 'SCH-APP-230', 'Leadership Grant', 'submitted', null, $admin->id);

        $create = $this->postJson('/api/scholarship/reviews', [
            'application_id' => $application->id,
            'score' => 88,
            'recommendation' => 'approve',
            'review_note' => 'Strong candidate',
        ]);

        $create->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.review.recommendation', 'approve');

        $reviewId = $create->json('data.review.id');

        $this->putJson('/api/scholarship/reviews/'.$reviewId, [
            'score' => 91,
            'review_note' => 'Updated score after review',
        ])->assertOk()
            ->assertJsonPath('data.review.score', 91);
    }

    public function test_teacher_scholarship_can_access_assigned_applications_only(): void
    {
        $teacher = $this->makeUserWithRole('teacher-scholarship', 'usr_740', 'teacher.scholarship740@hfccf.org');
        Sanctum::actingAs($teacher);

        $studentA = $this->createScholarshipStudent('SCH-STU-240', 'Assigned', 'Student');
        $studentB = $this->createScholarshipStudent('SCH-STU-241', 'Other', 'Student');

        $assigned = $this->createScholarshipApplication($studentA->id, 'SCH-APP-240', 'Academic Support', 'under_review', $teacher->id, $teacher->id);
        $unassigned = $this->createScholarshipApplication($studentB->id, 'SCH-APP-241', 'Transport Support', 'submitted', null, $teacher->id);

        $this->getJson('/api/scholarship/reviewer/my-applications')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['applicationCode' => 'SCH-APP-240'])
            ->assertJsonMissing(['applicationCode' => 'SCH-APP-241']);

        $this->getJson('/api/scholarship/applications/'.$assigned->id)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson('/api/scholarship/applications/'.$unassigned->id)
            ->assertNotFound();

        $this->postJson('/api/scholarship/students', [
            'first_name' => 'Forbidden',
            'last_name' => 'Student',
            'school_name' => 'Forbidden School',
            'grade_level' => 'Grade 1',
            'guardian_name' => 'Guard',
            'guardian_phone' => '+855 12 000 000',
            'address' => 'Phnom Penh',
            'status' => 'active',
        ])->assertForbidden();
    }

    public function test_unauthorized_users_are_blocked(): void
    {
        $this->getJson('/api/scholarship/dashboard')
            ->assertUnauthorized();
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

    private function createScholarshipStudent(string $code, string $firstName, string $lastName): object
    {
        $studentId = DB::table('scholarship_students')->insertGetId([
            'student_code' => $code,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'gender' => 'female',
            'date_of_birth' => '2008-01-01',
            'phone' => '+855 12 888 888',
            'email' => null,
            'school_name' => 'HFCCF School',
            'grade_level' => 'Grade 10',
            'guardian_name' => 'Guardian',
            'guardian_phone' => '+855 12 777 777',
            'address' => 'Phnom Penh',
            'status' => 'active',
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('scholarship_students')->where('id', $studentId)->first();
    }

    private function createScholarshipApplication(int $studentId, string $code, string $type, string $status, ?string $assignedReviewerId = null, ?string $changedByUserId = null): object
    {
        $applicationId = DB::table('scholarship_applications')->insertGetId([
            'student_id' => $studentId,
            'application_code' => $code,
            'scholarship_type' => $type,
            'requested_amount' => 1000,
            'academic_year' => '2025-2026',
            'submission_date' => '2026-05-14',
            'application_status' => $status,
            'assigned_reviewer_user_id' => $assignedReviewerId,
            'reviewed_at' => $status === 'draft' ? null : now(),
            'approved_at' => $status === 'approved' ? now() : null,
            'rejected_at' => $status === 'rejected' ? now() : null,
            'rejection_reason' => null,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($status !== 'draft') {
            DB::table('scholarship_status_histories')->insert([
                'application_id' => $applicationId,
                'previous_status' => 'draft',
                'new_status' => $status,
                'changed_by_user_id' => $changedByUserId ?? $assignedReviewerId ?? 'usr_700',
                'note' => null,
                'created_at' => now(),
            ]);
        }

        return DB::table('scholarship_applications')->where('id', $applicationId)->first();
    }
}
