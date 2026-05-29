<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PreschoolGovernanceCase extends Model
{
    protected $fillable = [
        'case_key',
        'title',
        'summary',
        'source_type',
        'source_reference',
        'source_context',
        'severity',
        'risk_score',
        'status',
        'is_urgent',
        'urgent_reason',
        'owner_user_id',
        'reviewer_user_id',
        'escalation_officer_user_id',
        'due_date',
        'academic_year_id',
        'term_id',
        'report_period_id',
        'class_id',
        'student_id',
        'created_by',
        'resolved_by',
        'resolved_at',
        'closed_by',
        'closed_at',
        'resolution_note',
        'latest_note',
    ];

    protected function casts(): array
    {
        return [
            'source_context' => 'array',
            'risk_score' => 'integer',
            'is_urgent' => 'boolean',
            'due_date' => 'date',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }

    public function escalationOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalation_officer_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by', 'id');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by', 'id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(PreschoolAcademicYear::class, 'academic_year_id');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(PreschoolAcademicTerm::class, 'term_id');
    }

    public function reportPeriod(): BelongsTo
    {
        return $this->belongsTo(PreschoolReportPeriod::class, 'report_period_id');
    }

    public function preschoolClass(): BelongsTo
    {
        return $this->belongsTo(PreschoolClass::class, 'class_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(PreschoolStudent::class, 'student_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PreschoolGovernanceCaseEvent::class, 'governance_case_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(PreschoolGovernanceCaseEvidence::class, 'governance_case_id');
    }
}
