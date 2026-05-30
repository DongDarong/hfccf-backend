<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PreschoolEnrollmentApplication extends Model
{
    use SoftDeletes;

    // Valid status transitions:
    // draft → submitted → under_review → approved → enrolled
    //                   ↘ rejected
    //                   ↘ waitlisted → approved → enrolled
    //                   → cancelled (from any non-terminal state)
    public const STATUSES = ['draft', 'submitted', 'under_review', 'approved', 'waitlisted', 'rejected', 'enrolled', 'cancelled'];

    public const TERMINAL_STATUSES = ['enrolled', 'rejected', 'cancelled'];

    protected $fillable = [
        'application_code',
        'first_name', 'last_name', 'khmer_name',
        'gender', 'date_of_birth', 'place_of_birth', 'nationality', 'avatar',
        'requested_academic_year_id', 'requested_term_id',
        'requested_level', 'preferred_class_id', 'requested_start_date',
        'guardian_name', 'guardian_relationship', 'guardian_phone',
        'guardian_email', 'guardian_address', 'guardian_can_pickup', 'guardian_is_emergency',
        'status', 'application_date', 'source',
        'admin_notes', 'rejection_reason', 'waitlist_reason',
        'reviewed_by_user_id', 'reviewed_at',
        'approved_by_user_id', 'approved_at',
        'enrolled_by_user_id', 'enrolled_at',
        'enrolled_student_id',
        'created_by_user_id', 'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'requested_start_date' => 'date',
            'application_date' => 'date',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'enrolled_at' => 'datetime',
            'guardian_can_pickup' => 'boolean',
            'guardian_is_emergency' => 'boolean',
        ];
    }

    public function requestedAcademicYear(): BelongsTo
    {
        return $this->belongsTo(PreschoolAcademicYear::class, 'requested_academic_year_id');
    }

    public function requestedTerm(): BelongsTo
    {
        return $this->belongsTo(PreschoolAcademicTerm::class, 'requested_term_id');
    }

    public function preferredClass(): BelongsTo
    {
        return $this->belongsTo(PreschoolClass::class, 'preferred_class_id');
    }

    public function enrolledStudent(): BelongsTo
    {
        return $this->belongsTo(PreschoolStudent::class, 'enrolled_student_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function enrolledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enrolled_by_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PreschoolEnrollmentDocument::class, 'application_id');
    }

    public function decisionLogs(): HasMany
    {
        return $this->hasMany(PreschoolEnrollmentDecisionLog::class, 'application_id')
            ->orderBy('recorded_at');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }
}
