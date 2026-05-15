<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScholarshipApplication extends Model
{
    use SoftDeletes;

    protected $table = 'scholarship_applications';

    protected $fillable = [
        'student_id',
        'application_code',
        'scholarship_type',
        'requested_amount',
        'academic_year',
        'submission_date',
        'application_status',
        'assigned_reviewer_user_id',
        'reviewed_at',
        'approved_at',
        'rejected_at',
        'rejection_reason',
        'notes',
    ];

    protected $casts = [
        'requested_amount' => 'decimal:2',
        'submission_date' => 'date',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(ScholarshipStudent::class, 'student_id');
    }

    public function assignedReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_reviewer_user_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ScholarshipReview::class, 'application_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ScholarshipStatusHistory::class, 'application_id');
    }
}
