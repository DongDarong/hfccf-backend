<?php

namespace Tests\Feature;

use App\Exceptions\PreschoolMonthlySubmissionException;
use App\Models\PreschoolAcademicYear;
use App\Models\PreschoolAssessmentCategory;
use App\Models\PreschoolClass;
use App\Models\PreschoolClassStudent;
use App\Models\PreschoolClassTeacherAssignment;
use App\Models\PreschoolMonthlySubmission;
use App\Models\PreschoolStudent;
use App\Services\PreschoolMonthlySubmissionService;
use App\Support\PreschoolMonthlySubmissionStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Models\PreschoolAssessmentGradingScale;
use Tests\TestCase;

class PreschoolMonthlySubmissionWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    private PreschoolMonthlySubmissionService $service;
    private PreschoolAcademicYear $academicYear;
    private PreschoolClass $class;
    private PreschoolAssessmentCategory $category;
    private PreschoolStudent $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PreschoolMonthlySubmissionService::class);

        // Create grading scales first
        PreschoolAssessmentGradingScale::factory()->count(5)->create();

        // Create test data
        $this->academicYear = PreschoolAcademicYear::factory()->create();
        $this->class = PreschoolClass::factory()->create();
        $this->category = PreschoolAssessmentCategory::factory()->active()->create();
        $this->student = PreschoolStudent::factory()->create();

        // Enroll student in class
        PreschoolClassStudent::create([
            'class_id' => $this->class->id,
            'student_id' => $this->student->id,
            'status' => 'active',
            'enrollment_status' => 'active',
        ]);
    }

    // ============================================================================
    // DRAFT CREATION TESTS
    // ============================================================================

    public function test_authorized_teacher_can_create_draft(): void
    {
        $teacher = $this->createTeacherForClass($this->class);

        $submission = $this->service->createDraft(
            $teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );

        $this->assertInstanceOf(PreschoolMonthlySubmission::class, $submission);
        $this->assertEquals(PreschoolMonthlySubmissionStatus::DRAFT, $submission->status);
        $this->assertEquals($this->class->id, $submission->class_id);
        $this->assertEquals($this->category->id, $submission->assessment_category_id);
    }

    public function test_unauthorized_teacher_cannot_create_draft(): void
    {
        $otherClass = PreschoolClass::factory()->create();
        $teacher = $this->createTeacherForClass($otherClass);

        $this->expectException(PreschoolMonthlySubmissionException::class);

        $this->service->createDraft(
            $teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
    }

    public function test_duplicate_draft_returns_existing_editable_submission(): void
    {
        $teacher = $this->createTeacherForClass($this->class);

        $submission1 = $this->service->createDraft(
            $teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );

        $submission2 = $this->service->createDraft(
            $teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );

        $this->assertEquals($submission1->id, $submission2->id);
    }

    public function test_submission_month_normalized_to_first_day(): void
    {
        $teacher = $this->createTeacherForClass($this->class);

        $submission = $this->service->createDraft(
            $teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );

        $this->assertEquals(1, $submission->submission_month->day);
    }

    public function test_inactive_category_cannot_create_draft(): void
    {
        $teacher = $this->createTeacherForClass($this->class);
        $inactiveCategory = PreschoolAssessmentCategory::factory()->inactive()->create();

        $this->expectException(PreschoolMonthlySubmissionException::class);

        $this->service->createDraft(
            $teacher,
            $this->academicYear,
            $this->class,
            $inactiveCategory
        );
    }

    // ============================================================================
    // SCORE EDITING TESTS
    // ============================================================================

    public function test_teacher_can_add_score_in_draft(): void
    {
        $teacher = $this->createTeacherForClass($this->class);
        $submission = $this->service->createDraft(
            $teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );

        $assessment = $this->service->addOrUpdateStudentScore(
            $teacher,
            $submission,
            $this->student,
            ['score' => 85.5, 'assessment_date' => now()->date()]
        );

        $this->assertNotNull($assessment->id);
        $this->assertEquals(85.5, $assessment->score);
        $this->assertEquals($this->student->id, $assessment->student_id);
        $this->assertEquals($submission->id, $assessment->monthly_submission_id);
    }

    public function test_teacher_cannot_edit_submitted_submission(): void
    {
        $teacher = $this->createTeacherForClass($this->class);
        $admin = $this->createAdmin();
        $submission = $this->service->createDraft(
            $teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
        $this->service->submit($teacher, $submission);

        $this->expectException(PreschoolMonthlySubmissionException::class);

        $this->service->addOrUpdateStudentScore(
            $teacher,
            $submission->refresh(),
            $this->student,
            ['score' => 90]
        );
    }

    public function test_teacher_can_edit_returned_submission(): void
    {
        $teacher = $this->createTeacherForClass($this->class);
        $admin = $this->createAdmin();
        $submission = $this->service->createDraft(
            $teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
        $this->service->submit($teacher, $submission);
        $this->service->returnForCorrection($admin, $submission->refresh(), 'Please revise');

        $assessment = $this->service->addOrUpdateStudentScore(
            $teacher,
            $submission->refresh(),
            $this->student,
            ['score' => 92]
        );

        $this->assertEquals(92, $assessment->score);
    }

    public function test_score_zero_handled_correctly(): void
    {
        $teacher = $this->createTeacherForClass($this->class);
        $submission = $this->service->createDraft(
            $teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );

        $assessment = $this->service->addOrUpdateStudentScore(
            $teacher,
            $submission,
            $this->student,
            ['score' => 0]
        );

        $this->assertEquals(0, $assessment->score);
    }

    public function test_invalid_score_rejected(): void
    {
        $teacher = $this->createTeacherForClass($this->class);
        $submission = $this->service->createDraft(
            $teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );

        $this->expectException(PreschoolMonthlySubmissionException::class);

        $this->service->addOrUpdateStudentScore(
            $teacher,
            $submission,
            $this->student,
            ['score' => -5]
        );
    }

    public function test_student_outside_class_rejected(): void
    {
        $teacher = $this->createTeacherForClass($this->class);
        $submission = $this->service->createDraft(
            $teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
        $otherStudent = PreschoolStudent::factory()->create();

        $this->expectException(PreschoolMonthlySubmissionException::class);

        $this->service->addOrUpdateStudentScore(
            $teacher,
            $submission,
            $otherStudent,
            ['score' => 85]
        );
    }

    // ============================================================================
    // SUBMIT TESTS
    // ============================================================================

    public function test_draft_can_submit(): void
    {
        $teacher = $this->createTeacherForClass($this->class);
        $submission = $this->service->createDraft(
            $teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
        $this->service->addOrUpdateStudentScore(
            $teacher,
            $submission,
            $this->student,
            ['score' => 85]
        );

        $result = $this->service->submit($teacher, $submission);

        $this->assertEquals(PreschoolMonthlySubmissionStatus::SUBMITTED, $result->status);
        $this->assertNotNull($result->submitted_at);
        $this->assertEquals($teacher->id, $result->submitted_by_user_id);
    }

    public function test_returned_can_resubmit(): void
    {
        $teacher = $this->createTeacherForClass($this->class);
        $admin = $this->createAdmin();
        $submission = $this->service->createDraft(
            $teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
        $this->service->addOrUpdateStudentScore(
            $teacher,
            $submission,
            $this->student,
            ['score' => 85]
        );
        $this->service->submit($teacher, $submission);
        $this->service->returnForCorrection($admin, $submission->refresh(), 'Revise');

        $result = $this->service->submit($teacher, $submission->refresh());

        $this->assertEquals(PreschoolMonthlySubmissionStatus::SUBMITTED, $result->status);
        $this->assertEquals($submission->id, $result->id); // Same parent record
    }

    public function test_empty_submission_cannot_submit(): void
    {
        $teacher = $this->createTeacherForClass($this->class);
        $submission = $this->service->createDraft(
            $teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );

        $this->expectException(PreschoolMonthlySubmissionException::class);

        $this->service->submit($teacher, $submission);
    }

    // ============================================================================
    // RETURN TESTS
    // ============================================================================

    public function test_admin_can_return_submitted(): void
    {
        $teacher = $this->createTeacherForClass($this->class);
        $admin = $this->createAdmin();
        $submission = $this->service->createDraft(
            $teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
        $this->service->addOrUpdateStudentScore(
            $teacher,
            $submission,
            $this->student,
            ['score' => 85]
        );
        $this->service->submit($teacher, $submission);

        $result = $this->service->returnForCorrection(
            $admin,
            $submission->refresh(),
            'Please check scores'
        );

        $this->assertEquals(PreschoolMonthlySubmissionStatus::RETURNED, $result->status);
        $this->assertEquals('Please check scores', $result->return_reason);
        $this->assertEquals($admin->id, $result->returned_by_user_id);
    }

    public function test_teacher_cannot_return(): void
    {
        $teacher = $this->createTeacherForClass($this->class);
        $submission = $this->service->createDraft(
            $teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
        $this->service->addOrUpdateStudentScore(
            $teacher,
            $submission,
            $this->student,
            ['score' => 85]
        );
        $this->service->submit($teacher, $submission);

        $this->expectException(PreschoolMonthlySubmissionException::class);

        $this->service->returnForCorrection(
            $teacher,
            $submission->refresh(),
            'Return'
        );
    }

    public function test_return_reason_required(): void
    {
        $admin = $this->createAdmin();
        $teacher = $this->createTeacherForClass($this->class);
        $submission = $this->service->createDraft(
            $teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
        $this->service->addOrUpdateStudentScore(
            $teacher,
            $submission,
            $this->student,
            ['score' => 85]
        );
        $this->service->submit($teacher, $submission);

        $this->expectException(PreschoolMonthlySubmissionException::class);

        $this->service->returnForCorrection($admin, $submission->refresh(), '');
    }

    // ============================================================================
    // FINALIZE TESTS
    // ============================================================================

    public function test_admin_can_finalize_submitted(): void
    {
        $teacher = $this->createTeacherForClass($this->class);
        $admin = $this->createAdmin();
        $submission = $this->service->createDraft(
            $teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
        $this->service->addOrUpdateStudentScore(
            $teacher,
            $submission,
            $this->student,
            ['score' => 85]
        );
        $this->service->submit($teacher, $submission);

        $result = $this->service->finalize($admin, $submission->refresh());

        $this->assertEquals(PreschoolMonthlySubmissionStatus::FINALIZED, $result->status);
        $this->assertNotNull($result->finalized_at);
        $this->assertNotNull($result->grading_scale_snapshot);
        $this->assertEquals($admin->id, $result->finalized_by_user_id);
    }

    public function test_teacher_cannot_finalize(): void
    {
        $teacher = $this->createTeacherForClass($this->class);
        $submission = $this->service->createDraft(
            $teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
        $this->service->addOrUpdateStudentScore(
            $teacher,
            $submission,
            $this->student,
            ['score' => 85]
        );
        $this->service->submit($teacher, $submission);

        $this->expectException(PreschoolMonthlySubmissionException::class);

        $this->service->finalize($teacher, $submission->refresh());
    }

    public function test_grading_snapshot_captured_on_finalize(): void
    {
        $teacher = $this->createTeacherForClass($this->class);
        $admin = $this->createAdmin();
        $submission = $this->service->createDraft(
            $teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
        $this->service->addOrUpdateStudentScore(
            $teacher,
            $submission,
            $this->student,
            ['score' => 85]
        );
        $this->service->submit($teacher, $submission);

        $result = $this->service->finalize($admin, $submission->refresh());

        $this->assertIsArray($result->grading_scale_snapshot);
        $this->assertArrayHasKey('captured_at', $result->grading_scale_snapshot);
        $this->assertArrayHasKey('scales', $result->grading_scale_snapshot);
        $this->assertCount(5, $result->grading_scale_snapshot['scales']); // A, B, C, D, F
    }

    // ============================================================================
    // ARCHIVE TESTS
    // ============================================================================

    public function test_admin_can_archive_finalized(): void
    {
        $teacher = $this->createTeacherForClass($this->class);
        $admin = $this->createAdmin();
        $submission = $this->service->createDraft(
            $teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
        $this->service->addOrUpdateStudentScore(
            $teacher,
            $submission,
            $this->student,
            ['score' => 85]
        );
        $this->service->submit($teacher, $submission);
        $this->service->finalize($admin, $submission->refresh());

        $this->service->archive($admin, $submission->refresh());

        $archivedSubmission = PreschoolMonthlySubmission::find($submission->id);
        $this->assertEquals(PreschoolMonthlySubmissionStatus::ARCHIVED, $archivedSubmission->status);
    }

    // ============================================================================
    // DELETE DRAFT TESTS
    // ============================================================================

    public function test_draft_can_be_deleted(): void
    {
        $teacher = $this->createTeacherForClass($this->class);
        $submission = $this->service->createDraft(
            $teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
        $this->service->addOrUpdateStudentScore(
            $teacher,
            $submission,
            $this->student,
            ['score' => 85]
        );

        $this->service->deleteDraft($teacher, $submission);

        $deletedSubmission = PreschoolMonthlySubmission::withTrashed()->find($submission->id);
        $this->assertNotNull($deletedSubmission->deleted_at);
    }

    public function test_returned_cannot_be_deleted(): void
    {
        $teacher = $this->createTeacherForClass($this->class);
        $admin = $this->createAdmin();
        $submission = $this->service->createDraft(
            $teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
        $this->service->addOrUpdateStudentScore(
            $teacher,
            $submission,
            $this->student,
            ['score' => 85]
        );
        $this->service->submit($teacher, $submission);
        $this->service->returnForCorrection($admin, $submission->refresh(), 'Revise');

        $this->expectException(PreschoolMonthlySubmissionException::class);

        $this->service->deleteDraft($teacher, $submission->refresh());
    }

    // ============================================================================
    // HELPER METHODS
    // ============================================================================

    private function createTeacherForClass(PreschoolClass $class): \App\Models\User
    {
        $user = \App\Models\User::factory()->create();
        $user->assignRole('teacher-preschool');

        PreschoolClassTeacherAssignment::create([
            'class_id' => $class->id,
            'teacher_user_id' => $user->id,
            'status' => 'active',
            'academic_year_id' => $this->academicYear->id,
        ]);

        return $user;
    }

    private function createAdmin(): \App\Models\User
    {
        $user = \App\Models\User::factory()->create();
        $user->assignRole('adminpreschool');
        return $user;
    }
}
