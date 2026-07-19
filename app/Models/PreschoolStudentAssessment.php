<?php

namespace App\Models;

use App\Support\PreschoolAssessmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PreschoolStudentAssessment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'monthly_submission_id',
        'student_id',
        'class_id',
        'category_id',
        'assessed_by_user_id',
        'period_label',
        'academic_year_id',
        'term_id',
        'assessment_date',
        'score',
        'rating',
        'observation',
        'teacher_comment',
        'status',
        'finalized_at',
        'finalized_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'assessment_date' => 'date',
            'score' => 'decimal:2',
            'finalized_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(PreschoolStudent::class, 'student_id');
    }

    public function preschoolClass(): BelongsTo
    {
        return $this->belongsTo(PreschoolClass::class, 'class_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PreschoolAssessmentCategory::class, 'category_id');
    }

    public function assessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by_user_id', 'id');
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by_user_id', 'id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(PreschoolAcademicYear::class, 'academic_year_id');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(PreschoolAcademicTerm::class, 'term_id');
    }

    public function monthlySubmission(): BelongsTo
    {
        return $this->belongsTo(PreschoolMonthlySubmission::class, 'monthly_submission_id');
    }

    public function isEditable(): bool
    {
        return $this->status === PreschoolAssessmentStatus::DRAFT;
    }
}
