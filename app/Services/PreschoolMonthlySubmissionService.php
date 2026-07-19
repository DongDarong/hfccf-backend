<?php

namespace App\Services;

use App\Exceptions\PreschoolMonthlySubmissionException;
use App\Models\PreschoolAcademicYear;
use App\Models\PreschoolAssessmentCategory;
use App\Models\PreschoolAssessmentGradingScale;
use App\Models\PreschoolClass;
use App\Models\PreschoolClassStudent;
use App\Models\PreschoolMonthlySubmission;
use App\Models\PreschoolStudent;
use App\Models\PreschoolStudentAssessment;
use App\Models\User;
use App\Support\PreschoolLifecycleAuditService;
use App\Support\PreschoolAssessmentConfigurationService;
use App\Support\PreschoolMonthlySubmissionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Centralizes workflow mutations for monthly assessment submissions.
 *
 * Responsibilities:
 * - Enforce status transitions (DRAFT → SUBMITTED → RETURNED → FINALIZED → ARCHIVED)
 * - Manage teacher and admin authorization
 * - Lock submissions appropriately by status
 * - Capture grading scale snapshots at finalization
 * - Integrate with lifecycle audit service
 * - Use database transactions for consistency
 */
class PreschoolMonthlySubmissionService
{
    public function __construct(
        private PreschoolLifecycleAuditService $auditService,
        private PreschoolAssessmentConfigurationService $assessmentConfigurationService,
    ) {}

    // ============================================================================
    // DRAFT CREATION
    // ============================================================================

    /**
     * Create a new monthly submission in DRAFT status.
     *
     * @param User $actor Teacher or admin creating the submission
     * @param PreschoolAcademicYear $academicYear The academic year context
     * @param PreschoolClass $preschoolClass The class for which scores are entered
     * @param PreschoolAssessmentCategory $category The assessment category
     * @return PreschoolMonthlySubmission
     * @throws PreschoolMonthlySubmissionException If creation fails
     */
    public function createDraft(
        User $actor,
        PreschoolAcademicYear $academicYear,
        PreschoolClass $preschoolClass,
        PreschoolAssessmentCategory $category
    ): PreschoolMonthlySubmission {
        // Authorization check
        if (!$this->canTeacherAccessClass($actor, $preschoolClass)) {
            throw PreschoolMonthlySubmissionException::unauthorized(
                'Teacher is not assigned to this class.'
            );
        }

        // Validate prerequisites
        if (!$academicYear->isActive()) {
            throw PreschoolMonthlySubmissionException::invalidAcademicYear(
                'Academic year is not active.'
            );
        }

        if (!$category->is_active) {
            throw PreschoolMonthlySubmissionException::invalidCategory(
                'Assessment category is not active.'
            );
        }

        // Normalize submission month to first day of month
        $submissionMonth = now()->startOfMonth();

        return DB::transaction(function () use (
            $actor,
            $academicYear,
            $preschoolClass,
            $category,
            $submissionMonth
        ) {
            // Check for existing submission (duplicate prevention)
            $existing = PreschoolMonthlySubmission::where([
                'academic_year_id' => $academicYear->id,
                'class_id' => $preschoolClass->id,
                'assessment_category_id' => $category->id,
                'submission_month' => $submissionMonth,
            ])->first();

            if ($existing && $existing->isEditable()) {
                // Return existing editable submission instead of failing
                // This allows teacher to continue where they left off
                return $existing;
            } elseif ($existing) {
                // Existing submission is not editable (submitted/finalized/archived)
                throw PreschoolMonthlySubmissionException::duplicateSubmission(
                    "A submission for this month already exists with status '{$existing->status}'."
                );
            }

            // Create new submission
            $submission = PreschoolMonthlySubmission::create([
                'academic_year_id' => $academicYear->id,
                'class_id' => $preschoolClass->id,
                'assessment_category_id' => $category->id,
                'submission_month' => $submissionMonth,
                'status' => PreschoolMonthlySubmissionStatus::DRAFT,
            ]);

            // Audit
            $this->auditService->recordSafely([
                'actor_user_id' => $actor->id,
                'actor_role' => $actor->role_code,
                'action_type' => 'monthly_submission_created',
                'entity_type' => 'preschool_monthly_submission',
                'entity_id' => (string) $submission->id,
                'previous_state' => null,
                'new_state' => [
                    'status' => $submission->status,
                    'academic_year_id' => $submission->academic_year_id,
                    'class_id' => $submission->class_id,
                    'category_id' => $submission->assessment_category_id,
                    'month' => $submission->submission_month->format('Y-m'),
                ],
                'request_context' => $this->auditService->requestContext(),
            ]);

            return $submission;
        });
    }

    // ============================================================================
    // STUDENT SCORE MUTATION (Add/Update)
    // ============================================================================

    /**
     * Add or update a student's score in an editable submission.
     *
     * @param User $actor Teacher entering scores
     * @param PreschoolMonthlySubmission $submission The monthly submission
     * @param PreschoolStudent $student The student being scored
     * @param array $scoreData Score, rating, observations, comments
     * @return PreschoolStudentAssessment
     * @throws PreschoolMonthlySubmissionException If mutation fails
     */
    public function addOrUpdateStudentScore(
        User $actor,
        PreschoolMonthlySubmission $submission,
        PreschoolStudent $student,
        array $scoreData
    ): PreschoolStudentAssessment {
        // Authorization & status check
        if (!$submission->isEditable()) {
            throw PreschoolMonthlySubmissionException::immutableSubmission(
                "Cannot edit submission with status '{$submission->status}'."
            );
        }

        // Verify teacher access to class
        if (!$this->canTeacherAccessClass($actor, $submission->class)) {
            throw PreschoolMonthlySubmissionException::unauthorized(
                'Teacher is not assigned to this class.'
            );
        }

        // Verify student belongs to class
        $enrollment = PreschoolClassStudent::where([
            'class_id' => $submission->class_id,
            'student_id' => $student->id,
            'status' => 'active',
            'enrollment_status' => 'active',
        ])->first();

        if (!$enrollment) {
            throw PreschoolMonthlySubmissionException::invalidStudentClass(
                'Student is not enrolled in this class or enrollment is inactive.'
            );
        }

        $score = (float) $scoreData['score'];
        $rating = $this->assessmentConfigurationService->getGradeForScore($score);
        if ($rating === null) {
            throw PreschoolMonthlySubmissionException::gradeScaleNotConfigured();
        }

        return DB::transaction(function () use ($submission, $student, $scoreData, $actor, $score, $rating) {
            $assessment = PreschoolStudentAssessment::updateOrCreate(
                [
                    'monthly_submission_id' => $submission->id,
                    'student_id' => $student->id,
                ],
                [
                    'class_id' => $submission->class_id,
                    'category_id' => $submission->assessment_category_id,
                    'assessed_by_user_id' => $actor->id,
                    'period_label' => $submission->submission_month->format('F Y'),
                    'academic_year_id' => $submission->academic_year_id,
                    'assessment_date' => $scoreData['assessment_date'] ?? now()->toDate(),
                    'score' => $score,
                    // Ratings are derived exclusively from the Admin-managed scale.
                    'rating' => $rating,
                    'observation' => $scoreData['observation'] ?? null,
                    'teacher_comment' => $scoreData['teacher_comment'] ?? null,
                    'status' => PreschoolMonthlySubmissionStatus::DRAFT,
                ]
            );

            $this->auditService->recordSafely([
                'actor_user_id' => $actor->id,
                'actor_role' => $actor->role_code,
                'action_type' => 'monthly_submission_score_updated',
                'entity_type' => 'student_assessment',
                'entity_id' => (string) $assessment->id,
                'previous_state' => $assessment->wasRecentlyCreated ? null : [
                    'score' => $assessment->getOriginal('score'),
                ],
                'new_state' => [
                    'score' => $assessment->score,
                    'monthly_submission_id' => $submission->id,
                    'student_id' => $student->id,
                ],
                'request_context' => $this->auditService->requestContext(),
            ]);

            return $assessment;
        });
    }

    // ============================================================================
    // SUBMIT TRANSITION (DRAFT/RETURNED → SUBMITTED)
    // ============================================================================

    /**
     * Submit a draft or returned submission to admin for review.
     *
     * @param User $actor Teacher submitting
     * @param PreschoolMonthlySubmission $submission
     * @return PreschoolMonthlySubmission
     * @throws PreschoolMonthlySubmissionException If submission fails
     */
    public function submit(
        User $actor,
        PreschoolMonthlySubmission $submission
    ): PreschoolMonthlySubmission {
        // Authorization & status check
        if (!$submission->canBeSubmitted()) {
            throw PreschoolMonthlySubmissionException::invalidStatusTransition(
                "Cannot submit from status '{$submission->status}'."
            );
        }

        if (!$this->canTeacherAccessClass($actor, $submission->class)) {
            throw PreschoolMonthlySubmissionException::unauthorized(
                'Teacher is not assigned to this class.'
            );
        }

        // Validate submission has at least one assessment
        $assessmentCount = $submission->studentAssessments()->count();
        if ($assessmentCount === 0) {
            throw PreschoolMonthlySubmissionException::emptySubmission(
                'Cannot submit a submission with no student assessments.'
            );
        }

        return DB::transaction(function () use ($actor, $submission) {
            // Re-read submission inside transaction to prevent race conditions
            $submission = PreschoolMonthlySubmission::lockForUpdate()->findOrFail($submission->id);

            if (!$submission->canBeSubmitted()) {
                throw PreschoolMonthlySubmissionException::invalidStatusTransition(
                    "Status changed to '{$submission->status}'; cannot submit."
                );
            }

            // For resubmission (RETURNED → SUBMITTED), update submission metadata
            $wasReturned = $submission->status === PreschoolMonthlySubmissionStatus::RETURNED;

            $submission->update([
                'status' => PreschoolMonthlySubmissionStatus::SUBMITTED,
                'submitted_at' => now(),
                'submitted_by_user_id' => $actor->id,
                // Note: preserve reviewed_at, returned_at, return_reason for history
            ]);

            $this->auditService->recordSafely([
                'actor_user_id' => $actor->id,
                'actor_role' => $actor->role_code,
                'action_type' => $wasReturned ? 'monthly_submission_resubmitted' : 'monthly_submission_submitted',
                'entity_type' => 'preschool_monthly_submission',
                'entity_id' => (string) $submission->id,
                'previous_state' => [
                    'status' => $wasReturned ? PreschoolMonthlySubmissionStatus::RETURNED : PreschoolMonthlySubmissionStatus::DRAFT,
                ],
                'new_state' => [
                    'status' => PreschoolMonthlySubmissionStatus::SUBMITTED,
                    'submitted_at' => $submission->submitted_at,
                ],
                'request_context' => $this->auditService->requestContext(),
            ]);

            return $submission;
        });
    }

    // ============================================================================
    // RETURN FOR CORRECTION (SUBMITTED → RETURNED)
    // ============================================================================

    /**
     * Return a submission to teacher for correction/revision.
     *
     * Admin only.
     *
     * @param User $actor Admin returning submission
     * @param PreschoolMonthlySubmission $submission
     * @param string $returnReason Required reason for return
     * @param string|null $reviewComment Optional admin feedback
     * @return PreschoolMonthlySubmission
     * @throws PreschoolMonthlySubmissionException If return fails
     */
    public function returnForCorrection(
        User $actor,
        PreschoolMonthlySubmission $submission,
        string $returnReason,
        ?string $reviewComment = null
    ): PreschoolMonthlySubmission {
        // Authorization & status check
        if (!$this->isAdminPreschool($actor)) {
            throw PreschoolMonthlySubmissionException::unauthorized(
                'Only Preschool Admin can return submissions.'
            );
        }

        if (!$submission->canBeReturned()) {
            throw PreschoolMonthlySubmissionException::invalidStatusTransition(
                "Cannot return from status '{$submission->status}'."
            );
        }

        if (blank($returnReason)) {
            throw PreschoolMonthlySubmissionException::invalidInput(
                'Return reason is required.'
            );
        }

        return DB::transaction(function () use ($actor, $submission, $returnReason, $reviewComment) {
            $submission = PreschoolMonthlySubmission::lockForUpdate()->findOrFail($submission->id);

            if (!$submission->canBeReturned()) {
                throw PreschoolMonthlySubmissionException::invalidStatusTransition(
                    "Status changed; cannot return."
                );
            }

            $submission->update([
                'status' => PreschoolMonthlySubmissionStatus::RETURNED,
                'reviewed_at' => now(),
                'reviewed_by_user_id' => $actor->id,
                'review_comment' => $reviewComment,
                'returned_at' => now(),
                'returned_by_user_id' => $actor->id,
                'return_reason' => $returnReason,
            ]);

            $this->auditService->recordSafely([
                'actor_user_id' => $actor->id,
                'actor_role' => $actor->role_code,
                'action_type' => 'monthly_submission_returned',
                'entity_type' => 'preschool_monthly_submission',
                'entity_id' => (string) $submission->id,
                'previous_state' => ['status' => PreschoolMonthlySubmissionStatus::SUBMITTED],
                'new_state' => [
                    'status' => PreschoolMonthlySubmissionStatus::RETURNED,
                    'return_reason' => $returnReason,
                ],
                'request_context' => $this->auditService->requestContext(),
            ]);

            return $submission;
        });
    }

    // ============================================================================
    // FINALIZE TRANSITION (SUBMITTED → FINALIZED)
    // ============================================================================

    /**
     * Finalize (approve) a submission, capturing grading scale snapshot.
     *
     * Admin only.
     *
     * @param User $actor Admin finalizing
     * @param PreschoolMonthlySubmission $submission
     * @param string|null $reviewComment Optional admin notes
     * @return PreschoolMonthlySubmission
     * @throws PreschoolMonthlySubmissionException If finalization fails
     */
    public function finalize(
        User $actor,
        PreschoolMonthlySubmission $submission,
        ?string $reviewComment = null
    ): PreschoolMonthlySubmission {
        // Authorization & status check
        if (!$this->isAdminPreschool($actor)) {
            throw PreschoolMonthlySubmissionException::unauthorized(
                'Only Preschool Admin can finalize submissions.'
            );
        }

        if (!$submission->canBeFinalized()) {
            throw PreschoolMonthlySubmissionException::invalidStatusTransition(
                "Cannot finalize from status '{$submission->status}'."
            );
        }

        // Validate submission has assessments
        $assessmentCount = $submission->studentAssessments()->count();
        if ($assessmentCount === 0) {
            throw PreschoolMonthlySubmissionException::emptySubmission(
                'Cannot finalize a submission with no assessments.'
            );
        }

        return DB::transaction(function () use ($actor, $submission, $reviewComment) {
            $submission = PreschoolMonthlySubmission::lockForUpdate()->findOrFail($submission->id);

            if (!$submission->canBeFinalized()) {
                throw PreschoolMonthlySubmissionException::invalidStatusTransition(
                    "Status changed; cannot finalize."
                );
            }

            // Capture grading scale snapshot
            $gradingScaleSnapshot = $this->captureGradingScaleSnapshot();

            $submission->update([
                'status' => PreschoolMonthlySubmissionStatus::FINALIZED,
                'reviewed_at' => now(),
                'reviewed_by_user_id' => $actor->id,
                'review_comment' => $reviewComment,
                'finalized_at' => now(),
                'finalized_by_user_id' => $actor->id,
                'locked_at' => now(),
                'grading_scale_snapshot' => $gradingScaleSnapshot,
            ]);

            $this->auditService->recordSafely([
                'actor_user_id' => $actor->id,
                'actor_role' => $actor->role_code,
                'action_type' => 'monthly_submission_finalized',
                'entity_type' => 'preschool_monthly_submission',
                'entity_id' => (string) $submission->id,
                'previous_state' => ['status' => PreschoolMonthlySubmissionStatus::SUBMITTED],
                'new_state' => [
                    'status' => PreschoolMonthlySubmissionStatus::FINALIZED,
                    'finalized_at' => $submission->finalized_at,
                    'grading_scale_captured' => true,
                ],
                'request_context' => $this->auditService->requestContext(),
            ]);

            return $submission;
        });
    }

    // ============================================================================
    // ARCHIVE TRANSITION (FINALIZED → ARCHIVED)
    // ============================================================================

    /**
     * Archive a finalized submission (soft-delete state).
     *
     * Admin only.
     *
     * @param User $actor Admin archiving
     * @param PreschoolMonthlySubmission $submission
     * @return void
     * @throws PreschoolMonthlySubmissionException If archive fails
     */
    public function archive(
        User $actor,
        PreschoolMonthlySubmission $submission
    ): PreschoolMonthlySubmission {
        // Authorization & status check
        if (!$this->isAdminPreschool($actor)) {
            throw PreschoolMonthlySubmissionException::unauthorized(
                'Only Preschool Admin can archive submissions.'
            );
        }

        if (!$submission->canBeArchived()) {
            throw PreschoolMonthlySubmissionException::invalidStatusTransition(
                "Cannot archive from status '{$submission->status}'."
            );
        }

        return DB::transaction(function () use ($actor, $submission) {
            $submission = PreschoolMonthlySubmission::lockForUpdate()->findOrFail($submission->id);

            if (!$submission->canBeArchived()) {
                throw PreschoolMonthlySubmissionException::invalidStatusTransition(
                    "Status changed; cannot archive."
                );
            }

            $submission->update([
                'status' => PreschoolMonthlySubmissionStatus::ARCHIVED,
            ]);

            $this->auditService->recordSafely([
                'actor_user_id' => $actor->id,
                'actor_role' => $actor->role_code,
                'action_type' => 'monthly_submission_archived',
                'entity_type' => 'preschool_monthly_submission',
                'entity_id' => (string) $submission->id,
                'previous_state' => ['status' => $submission->status],
                'new_state' => ['status' => PreschoolMonthlySubmissionStatus::ARCHIVED],
                'request_context' => $this->auditService->requestContext(),
            ]);

            return $submission;
        });
    }

    // ============================================================================
    // DELETE DRAFT
    // ============================================================================

    /**
     * Delete (soft-delete) a draft submission and its child assessments.
     *
     * @param User $actor Teacher or admin deleting
     * @param PreschoolMonthlySubmission $submission
     * @return void
     * @throws PreschoolMonthlySubmissionException If delete fails
     */
    public function deleteDraft(
        User $actor,
        PreschoolMonthlySubmission $submission
    ): void {
        // Status check
        if ($submission->status !== PreschoolMonthlySubmissionStatus::DRAFT) {
            throw PreschoolMonthlySubmissionException::invalidStatusTransition(
                "Only DRAFT submissions can be deleted; current status is '{$submission->status}'."
            );
        }

        // Authorization
        if (!$this->canTeacherAccessClass($actor, $submission->class) && !$this->isAdminPreschool($actor)) {
            throw PreschoolMonthlySubmissionException::unauthorized(
                'You are not authorized to delete this submission.'
            );
        }

        DB::transaction(function () use ($actor, $submission) {
            // Soft-delete child assessments
            $submission->studentAssessments()->delete();

            // Soft-delete submission
            $submission->delete();

            $this->auditService->recordSafely([
                'actor_user_id' => $actor->id,
                'actor_role' => $actor->role_code,
                'action_type' => 'monthly_submission_draft_deleted',
                'entity_type' => 'preschool_monthly_submission',
                'entity_id' => (string) $submission->id,
                'previous_state' => ['status' => PreschoolMonthlySubmissionStatus::DRAFT],
                'new_state' => ['deleted_at' => now()],
                'request_context' => $this->auditService->requestContext(),
            ]);
        });
    }

    // ============================================================================
    // HELPER METHODS
    // ============================================================================

    /**
     * Check if teacher is currently assigned to class.
     */
    private function canTeacherAccessClass(User $actor, PreschoolClass $class): bool
    {
        if ($this->isAdminPreschool($actor)) {
            return true; // Admin can access all classes
        }

        return $actor->preschoolClassTeacherAssignments()
            ->where('class_id', $class->id)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Check if actor is Preschool Admin or Super Admin.
     */
    private function isAdminPreschool(User $actor): bool
    {
        return in_array($actor->role_code, ['adminpreschool', 'superadmin'], true);
    }

    /**
     * Capture current grading scale as immutable JSON snapshot.
     */
    private function captureGradingScaleSnapshot(): array
    {
        $scales = PreschoolAssessmentGradingScale::orderBy('sort_order')->get();

        return [
            'captured_at' => now()->toIso8601String(),
            'scales' => $scales->map(fn ($scale) => [
                'grade' => $scale->grade,
                'minimum_score' => $scale->minimum_score,
                'maximum_score' => $scale->maximum_score,
                'is_passing' => $scale->is_passing,
            ])->all(),
        ];
    }
}
