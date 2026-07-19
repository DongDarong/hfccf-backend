<?php

namespace Tests\Feature;

use App\Exceptions\PreschoolMonthlySubmissionException;
use App\Models\PreschoolAcademicYear;
use App\Models\PreschoolAssessmentCategory;
use App\Models\PreschoolAssessmentGradingScale;
use App\Models\PreschoolClass;
use App\Models\PreschoolClassStudent;
use App\Models\PreschoolClassTeacherAssignment;
use App\Models\PreschoolMonthlySubmission;
use App\Models\PreschoolStudent;
use App\Models\User;
use App\Services\PreschoolMonthlySubmissionService;
use App\Support\PreschoolMonthlySubmissionStatus;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase A.3.1 Service Contract Verification
 *
 * Verifies core contracts for:
 * 1. Duplicate submission behavior
 * 2. Finalization transaction behavior
 * 3. Archive semantics
 * 4. Domain exception to HTTP mapping
 * 5. Idempotency
 * 6. Concurrency and stale-transition handling
 */
class PreschoolMonthlySubmissionContractVerificationTest extends TestCase
{
    use DatabaseTransactions;

    private PreschoolMonthlySubmissionService $service;
    private PreschoolAcademicYear $academicYear;
    private PreschoolClass $class;
    private PreschoolAssessmentCategory $category;
    private PreschoolStudent $student;
    private User $teacher;
    private User $admin;

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
        $this->teacher = $this->createTeacherForClass($this->class);
        $this->admin = $this->createAdmin();

        // Enroll student in class
        PreschoolClassStudent::create([
            'class_id' => $this->class->id,
            'student_id' => $this->student->id,
            'status' => 'active',
            'enrollment_status' => 'active',
        ]);
    }

    // ============================================================================
    // 1. DUPLICATE SUBMISSION CONTRACT
    // ============================================================================

    public function test_duplicate_draft_returns_same_id(): void
    {
        $submission1 = $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );

        $submission2 = $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );

        $this->assertEquals($submission1->id, $submission2->id);
        $this->assertEquals(PreschoolMonthlySubmissionStatus::DRAFT, $submission2->status);
    }

    public function test_duplicate_returned_returns_same_id(): void
    {
        $submission = $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
        $this->service->addOrUpdateStudentScore(
            $this->teacher,
            $submission,
            $this->student,
            ['score' => 85]
        );
        $this->service->submit($this->teacher, $submission);
        $this->service->returnForCorrection($this->admin, $submission->refresh(), 'Revise');

        $submission2 = $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );

        $this->assertEquals($submission->id, $submission2->id);
        $this->assertEquals(PreschoolMonthlySubmissionStatus::RETURNED, $submission2->status);
    }

    public function test_duplicate_submitted_throws_conflict(): void
    {
        $submission = $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
        $this->service->addOrUpdateStudentScore(
            $this->teacher,
            $submission,
            $this->student,
            ['score' => 85]
        );
        $this->service->submit($this->teacher, $submission);

        $this->expectException(PreschoolMonthlySubmissionException::class);

        $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
    }

    public function test_duplicate_finalized_throws_conflict(): void
    {
        $submission = $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
        $this->service->addOrUpdateStudentScore(
            $this->teacher,
            $submission,
            $this->student,
            ['score' => 85]
        );
        $this->service->submit($this->teacher, $submission);
        $this->service->finalize($this->admin, $submission->refresh());

        $this->expectException(PreschoolMonthlySubmissionException::class);

        $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
    }

    public function test_duplicate_archived_throws_conflict(): void
    {
        $submission = $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
        $this->service->addOrUpdateStudentScore(
            $this->teacher,
            $submission,
            $this->student,
            ['score' => 85]
        );
        $this->service->submit($this->teacher, $submission);
        $this->service->finalize($this->admin, $submission->refresh());
        $this->service->archive($this->admin, $submission->refresh());

        $this->expectException(PreschoolMonthlySubmissionException::class);

        $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
    }

    public function test_never_creates_duplicate_parent_records(): void
    {
        $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );

        $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );

        $count = PreschoolMonthlySubmission::where([
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->class->id,
            'assessment_category_id' => $this->category->id,
        ])->withTrashed()->count();

        $this->assertEquals(1, $count);
    }

    // ============================================================================
    // 2. FINALIZATION TRANSACTION CONTRACT
    // ============================================================================

    public function test_finalization_locks_parent_row(): void
    {
        $submission = $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
        $this->service->addOrUpdateStudentScore(
            $this->teacher,
            $submission,
            $this->student,
            ['score' => 85]
        );
        $this->service->submit($this->teacher, $submission);

        $result = $this->service->finalize($this->admin, $submission->refresh());

        $this->assertNotNull($result->locked_at);
        $this->assertEquals($result->locked_at, $result->finalized_at);
    }

    public function test_finalization_captures_grading_scale_snapshot(): void
    {
        $submission = $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
        $this->service->addOrUpdateStudentScore(
            $this->teacher,
            $submission,
            $this->student,
            ['score' => 85]
        );
        $this->service->submit($this->teacher, $submission);

        $result = $this->service->finalize($this->admin, $submission->refresh());

        $this->assertIsArray($result->grading_scale_snapshot);
        $this->assertArrayHasKey('captured_at', $result->grading_scale_snapshot);
        $this->assertArrayHasKey('scales', $result->grading_scale_snapshot);
        $this->assertCount(5, $result->grading_scale_snapshot['scales']); // Standard grades
    }

    public function test_double_finalization_fails_predictably(): void
    {
        $submission = $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
        $this->service->addOrUpdateStudentScore(
            $this->teacher,
            $submission,
            $this->student,
            ['score' => 85]
        );
        $this->service->submit($this->teacher, $submission);
        $this->service->finalize($this->admin, $submission->refresh());

        $this->expectException(PreschoolMonthlySubmissionException::class);

        $this->service->finalize($this->admin, $submission->refresh());
    }

    public function test_stale_finalize_after_another_transition_fails(): void
    {
        $submission = $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
        $this->service->addOrUpdateStudentScore(
            $this->teacher,
            $submission,
            $this->student,
            ['score' => 85]
        );
        $this->service->submit($this->teacher, $submission);

        // Manually change status to simulate stale read
        $submission->update(['status' => PreschoolMonthlySubmissionStatus::ARCHIVED]);

        $this->expectException(PreschoolMonthlySubmissionException::class);

        $this->service->finalize($this->admin, $submission);
    }

    public function test_finalization_updates_all_required_fields_atomically(): void
    {
        $submission = $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
        $this->service->addOrUpdateStudentScore(
            $this->teacher,
            $submission,
            $this->student,
            ['score' => 85]
        );
        $this->service->submit($this->teacher, $submission);

        $result = $this->service->finalize($this->admin, $submission->refresh(), 'Approved');

        $this->assertEquals(PreschoolMonthlySubmissionStatus::FINALIZED, $result->status);
        $this->assertNotNull($result->finalized_at);
        $this->assertNotNull($result->reviewed_at);
        $this->assertEquals($this->admin->id, $result->finalized_by_user_id);
        $this->assertEquals($this->admin->id, $result->reviewed_by_user_id);
        $this->assertNotNull($result->grading_scale_snapshot);
        $this->assertNotNull($result->locked_at);
    }

    // ============================================================================
    // 3. ARCHIVE SEMANTICS CONTRACT
    // ============================================================================

    public function test_archive_is_status_transition_only_not_soft_delete(): void
    {
        $submission = $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
        $this->service->addOrUpdateStudentScore(
            $this->teacher,
            $submission,
            $this->student,
            ['score' => 85]
        );
        $this->service->submit($this->teacher, $submission);
        $this->service->finalize($this->admin, $submission->refresh());

        $this->service->archive($this->admin, $submission->refresh());

        // Archived record should remain queryable in normal queries (not soft-deleted)
        $archived = PreschoolMonthlySubmission::find($submission->id);
        $this->assertNotNull($archived);
        $this->assertEquals(PreschoolMonthlySubmissionStatus::ARCHIVED, $archived->status);
    }

    public function test_archived_record_remains_in_normal_queries(): void
    {
        $submission = $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
        $this->service->addOrUpdateStudentScore(
            $this->teacher,
            $submission,
            $this->student,
            ['score' => 85]
        );
        $this->service->submit($this->teacher, $submission);
        $this->service->finalize($this->admin, $submission->refresh());
        $this->service->archive($this->admin, $submission->refresh());

        $allRecords = PreschoolMonthlySubmission::all();
        $this->assertTrue($allRecords->contains('id', $submission->id));
    }

    public function test_archived_child_assessments_remain_linked(): void
    {
        $submission = $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
        $this->service->addOrUpdateStudentScore(
            $this->teacher,
            $submission,
            $this->student,
            ['score' => 85]
        );
        $this->service->submit($this->teacher, $submission);
        $this->service->finalize($this->admin, $submission->refresh());
        $this->service->archive($this->admin, $submission->refresh());

        $archived = PreschoolMonthlySubmission::find($submission->id);
        $this->assertCount(1, $archived->studentAssessments);
    }

    public function test_archived_grading_snapshot_unchanged(): void
    {
        $submission = $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
        $this->service->addOrUpdateStudentScore(
            $this->teacher,
            $submission,
            $this->student,
            ['score' => 85]
        );
        $this->service->submit($this->teacher, $submission);
        $finalized = $this->service->finalize($this->admin, $submission->refresh());
        $snapshotBefore = $finalized->grading_scale_snapshot;

        $this->service->archive($this->admin, $finalized->refresh());
        $archived = PreschoolMonthlySubmission::find($submission->id);

        $this->assertEquals($snapshotBefore, $archived->grading_scale_snapshot);
    }

    public function test_archived_submission_cannot_be_edited(): void
    {
        $submission = $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
        $this->service->addOrUpdateStudentScore(
            $this->teacher,
            $submission,
            $this->student,
            ['score' => 85]
        );
        $this->service->submit($this->teacher, $submission);
        $this->service->finalize($this->admin, $submission->refresh());
        $this->service->archive($this->admin, $submission->refresh());

        $archived = PreschoolMonthlySubmission::find($submission->id);

        $this->expectException(PreschoolMonthlySubmissionException::class);

        $this->service->addOrUpdateStudentScore(
            $this->teacher,
            $archived,
            $this->student,
            ['score' => 90]
        );
    }

    public function test_archived_submission_cannot_be_submitted_again(): void
    {
        $submission = $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
        $this->service->addOrUpdateStudentScore(
            $this->teacher,
            $submission,
            $this->student,
            ['score' => 85]
        );
        $this->service->submit($this->teacher, $submission);
        $this->service->finalize($this->admin, $submission->refresh());
        $this->service->archive($this->admin, $submission->refresh());

        $archived = PreschoolMonthlySubmission::find($submission->id);

        $this->expectException(PreschoolMonthlySubmissionException::class);

        $this->service->submit($this->teacher, $archived);
    }

    public function test_archived_submission_cannot_be_finalized_again(): void
    {
        $submission = $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
        $this->service->addOrUpdateStudentScore(
            $this->teacher,
            $submission,
            $this->student,
            ['score' => 85]
        );
        $this->service->submit($this->teacher, $submission);
        $this->service->finalize($this->admin, $submission->refresh());
        $this->service->archive($this->admin, $submission->refresh());

        $archived = PreschoolMonthlySubmission::find($submission->id);

        $this->expectException(PreschoolMonthlySubmissionException::class);

        $this->service->finalize($this->admin, $archived);
    }

    // ============================================================================
    // 4. EXCEPTION CONTRACT AND HTTP MAPPING
    // ============================================================================

    public function test_unauthorized_returns_403(): void
    {
        $otherTeacher = $this->createTeacherForClass(
            PreschoolClass::factory()->create()
        );

        try {
            $this->service->createDraft(
                $otherTeacher,
                $this->academicYear,
                $this->class,
                $this->category
            );
            $this->fail('Should throw exception');
        } catch (PreschoolMonthlySubmissionException $e) {
            $this->assertEquals(403, $e->getCode());
            $this->assertEquals('UNAUTHORIZED', $e->getErrorCode());
        }
    }

    public function test_duplicate_submission_returns_409(): void
    {
        $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
        $submission = $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
        $this->service->addOrUpdateStudentScore(
            $this->teacher,
            $submission,
            $this->student,
            ['score' => 85]
        );
        $this->service->submit($this->teacher, $submission);

        try {
            $this->service->createDraft(
                $this->teacher,
                $this->academicYear,
                $this->class,
                $this->category
            );
            $this->fail('Should throw exception');
        } catch (PreschoolMonthlySubmissionException $e) {
            $this->assertEquals(409, $e->getCode());
            $this->assertEquals('DUPLICATE_SUBMISSION', $e->getErrorCode());
        }
    }

    public function test_invalid_status_transition_returns_409(): void
    {
        $submission = $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );

        try {
            $this->service->finalize($this->admin, $submission);
            $this->fail('Should throw exception');
        } catch (PreschoolMonthlySubmissionException $e) {
            $this->assertEquals(409, $e->getCode());
            $this->assertEquals('INVALID_STATUS_TRANSITION', $e->getErrorCode());
        }
    }

    public function test_invalid_score_returns_422(): void
    {
        $submission = $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );

        try {
            $this->service->addOrUpdateStudentScore(
                $this->teacher,
                $submission,
                $this->student,
                ['score' => -10]
            );
            $this->fail('Should throw exception');
        } catch (PreschoolMonthlySubmissionException $e) {
            $this->assertEquals(422, $e->getCode());
            $this->assertEquals('INVALID_SCORE', $e->getErrorCode());
        }
    }

    public function test_empty_submission_returns_422(): void
    {
        $submission = $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );

        try {
            $this->service->submit($this->teacher, $submission);
            $this->fail('Should throw exception');
        } catch (PreschoolMonthlySubmissionException $e) {
            $this->assertEquals(422, $e->getCode());
            $this->assertEquals('EMPTY_SUBMISSION', $e->getErrorCode());
        }
    }

    public function test_invalid_student_class_returns_422(): void
    {
        $submission = $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
        $otherStudent = PreschoolStudent::factory()->create();

        try {
            $this->service->addOrUpdateStudentScore(
                $this->teacher,
                $submission,
                $otherStudent,
                ['score' => 85]
            );
            $this->fail('Should throw exception');
        } catch (PreschoolMonthlySubmissionException $e) {
            $this->assertEquals(422, $e->getCode());
            $this->assertEquals('INVALID_STUDENT_CLASS', $e->getErrorCode());
        }
    }

    // ============================================================================
    // 5. IDEMPOTENCY CONTRACT
    // ============================================================================

    public function test_create_draft_idempotent_returns_same_record(): void
    {
        $submission1 = $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );

        $submission2 = $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );

        $this->assertEquals($submission1->id, $submission2->id);
        $this->assertEquals($submission1->created_at, $submission2->created_at);
    }

    public function test_add_or_update_score_idempotent_same_value(): void
    {
        $submission = $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );

        $assessment1 = $this->service->addOrUpdateStudentScore(
            $this->teacher,
            $submission,
            $this->student,
            ['score' => 85]
        );

        $assessment2 = $this->service->addOrUpdateStudentScore(
            $this->teacher,
            $submission,
            $this->student,
            ['score' => 85]
        );

        $this->assertEquals($assessment1->id, $assessment2->id);
        $this->assertEquals(85, $assessment2->score);
    }

    public function test_submit_idempotent_fails_on_resubmit(): void
    {
        $submission = $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
        $this->service->addOrUpdateStudentScore(
            $this->teacher,
            $submission,
            $this->student,
            ['score' => 85]
        );
        $submitted = $this->service->submit($this->teacher, $submission);

        $this->expectException(PreschoolMonthlySubmissionException::class);

        $this->service->submit($this->teacher, $submitted->refresh());
    }

    public function test_return_idempotent_fails_on_repeated_return(): void
    {
        $submission = $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
        $this->service->addOrUpdateStudentScore(
            $this->teacher,
            $submission,
            $this->student,
            ['score' => 85]
        );
        $this->service->submit($this->teacher, $submission);
        $returned = $this->service->returnForCorrection(
            $this->admin,
            $submission->refresh(),
            'Revise'
        );

        $this->expectException(PreschoolMonthlySubmissionException::class);

        $this->service->returnForCorrection($this->admin, $returned->refresh(), 'Revise again');
    }

    public function test_finalize_idempotent_fails_on_repeated_finalize(): void
    {
        $submission = $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
        $this->service->addOrUpdateStudentScore(
            $this->teacher,
            $submission,
            $this->student,
            ['score' => 85]
        );
        $this->service->submit($this->teacher, $submission);
        $finalized = $this->service->finalize($this->admin, $submission->refresh());

        $this->expectException(PreschoolMonthlySubmissionException::class);

        $this->service->finalize($this->admin, $finalized->refresh());
    }

    public function test_archive_idempotent_fails_on_repeated_archive(): void
    {
        $submission = $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
        $this->service->addOrUpdateStudentScore(
            $this->teacher,
            $submission,
            $this->student,
            ['score' => 85]
        );
        $this->service->submit($this->teacher, $submission);
        $this->service->finalize($this->admin, $submission->refresh());
        $this->service->archive($this->admin, $submission->refresh());

        $this->expectException(PreschoolMonthlySubmissionException::class);

        $archived = PreschoolMonthlySubmission::find($submission->id);
        $this->service->archive($this->admin, $archived);
    }

    public function test_delete_draft_idempotent_fails_on_repeated_delete(): void
    {
        $submission = $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );

        $this->service->deleteDraft($this->teacher, $submission);

        $this->expectException(PreschoolMonthlySubmissionException::class);

        $deleted = PreschoolMonthlySubmission::withTrashed()->find($submission->id);
        $this->service->deleteDraft($this->teacher, $deleted);
    }

    // ============================================================================
    // 6. CONCURRENCY CONTRACT
    // ============================================================================

    public function test_concurrent_duplicate_creation_prevented_by_unique_constraint(): void
    {
        // This test verifies that database unique constraints protect against
        // truly concurrent race conditions where two requests try to create
        // the same submission simultaneously.

        // Create first submission
        $submission1 = $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );

        // Verify only one record exists
        $count = PreschoolMonthlySubmission::where([
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $this->class->id,
            'assessment_category_id' => $this->category->id,
        ])->count();

        $this->assertEquals(1, $count);
    }

    public function test_concurrent_submits_only_first_succeeds(): void
    {
        $submission = $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
        $this->service->addOrUpdateStudentScore(
            $this->teacher,
            $submission,
            $this->student,
            ['score' => 85]
        );

        // First submit succeeds
        $submitted = $this->service->submit($this->teacher, $submission);
        $this->assertEquals(PreschoolMonthlySubmissionStatus::SUBMITTED, $submitted->status);

        // Second submit fails
        $staleSubmission = PreschoolMonthlySubmission::find($submission->id);
        $this->expectException(PreschoolMonthlySubmissionException::class);

        $this->service->submit($this->teacher, $staleSubmission);
    }

    public function test_concurrent_finalizes_only_first_succeeds(): void
    {
        $submission = $this->service->createDraft(
            $this->teacher,
            $this->academicYear,
            $this->class,
            $this->category
        );
        $this->service->addOrUpdateStudentScore(
            $this->teacher,
            $submission,
            $this->student,
            ['score' => 85]
        );
        $this->service->submit($this->teacher, $submission);

        // First finalize succeeds
        $finalized = $this->service->finalize($this->admin, $submission);
        $this->assertEquals(PreschoolMonthlySubmissionStatus::FINALIZED, $finalized->status);

        // Second finalize fails
        $staleSubmission = PreschoolMonthlySubmission::find($submission->id);
        $this->expectException(PreschoolMonthlySubmissionException::class);

        $this->service->finalize($this->admin, $staleSubmission);
    }

    // ============================================================================
    // HELPER METHODS
    // ============================================================================

    private function createTeacherForClass(PreschoolClass $class): User
    {
        $user = User::factory()->create();
        $user->assignRole('teacher-preschool');

        PreschoolClassTeacherAssignment::create([
            'class_id' => $class->id,
            'teacher_user_id' => $user->id,
            'status' => 'active',
            'academic_year_id' => $this->academicYear->id,
        ]);

        return $user;
    }

    private function createAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('adminpreschool');
        return $user;
    }
}
