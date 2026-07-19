<?php

namespace App\Models;

use App\Support\PreschoolMonthlySubmissionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PreschoolMonthlySubmission extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'preschool_monthly_submissions';

    /**
     * Submission identity and workflow metadata.
     *
     * submitted_by_user_id typically equals the teacher assigned to the class,
     * but may differ if a substitute or admin created the submission.
     */
    protected $fillable = [
        'academic_year_id',
        'class_id',
        'assessment_category_id',
        'submission_month',

        // Submission
        'submitted_at',
        'submitted_by_user_id',

        // Review
        'reviewed_at',
        'reviewed_by_user_id',
        'review_comment',

        // Return
        'returned_at',
        'returned_by_user_id',
        'return_reason',

        // Finalization
        'finalized_at',
        'finalized_by_user_id',

        // Status & Snapshots
        'status',
        'locked_at',
        'grading_scale_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'academic_year_id' => 'integer',
            'class_id' => 'integer',
            'assessment_category_id' => 'integer',
            'submission_month' => 'date',

            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'returned_at' => 'datetime',
            'finalized_at' => 'datetime',
            'locked_at' => 'datetime',

            'grading_scale_snapshot' => 'array',
        ];
    }

    /**
     * Status must be one of the canonical workflow states.
     */
    public function isValidStatus(?string $status): bool
    {
        return $status !== null && in_array($status, PreschoolMonthlySubmissionStatus::values(), true);
    }

    /**
     * Can the submission be edited by teachers?
     */
    public function isEditable(): bool
    {
        return in_array($this->status, [
            PreschoolMonthlySubmissionStatus::DRAFT,
            PreschoolMonthlySubmissionStatus::RETURNED,
        ], true);
    }

    /**
     * Can the submission be submitted by teachers?
     */
    public function canBeSubmitted(): bool
    {
        return in_array($this->status, [
            PreschoolMonthlySubmissionStatus::DRAFT,
            PreschoolMonthlySubmissionStatus::RETURNED,
        ], true);
    }

    /**
     * Can the submission be reviewed by admins?
     */
    public function canBeReviewed(): bool
    {
        return $this->status === PreschoolMonthlySubmissionStatus::SUBMITTED;
    }

    /**
     * Can the submission be returned for revision?
     */
    public function canBeReturned(): bool
    {
        return $this->status === PreschoolMonthlySubmissionStatus::SUBMITTED;
    }

    /**
     * Can the submission be finalized (approved)?
     */
    public function canBeFinalized(): bool
    {
        return $this->status === PreschoolMonthlySubmissionStatus::SUBMITTED;
    }

    /**
     * Can the submission be archived?
     */
    public function canBeArchived(): bool
    {
        return in_array($this->status, [
            PreschoolMonthlySubmissionStatus::FINALIZED,
            PreschoolMonthlySubmissionStatus::DRAFT,
        ], true);
    }

    // ============================================================================
    // RELATIONSHIPS
    // ============================================================================

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(PreschoolAcademicYear::class, 'academic_year_id');
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(PreschoolClass::class, 'class_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PreschoolAssessmentCategory::class, 'assessment_category_id');
    }

    /**
     * Student assessments linked to this monthly submission.
     */
    public function studentAssessments(): HasMany
    {
        return $this->hasMany(PreschoolStudentAssessment::class, 'monthly_submission_id');
    }

    /**
     * Teacher who submitted this submission.
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id', 'id');
    }

    /**
     * Admin who reviewed this submission.
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id', 'id');
    }

    /**
     * Admin who returned this submission for revision.
     */
    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by_user_id', 'id');
    }

    /**
     * Admin who finalized this submission.
     */
    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by_user_id', 'id');
    }
}
