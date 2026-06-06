<?php

namespace App\Models\Dsam;

use App\Models\AcademicYear;
use App\Models\PreschoolStudent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class FormSubmission extends Model
{
    use SoftDeletes;

    protected $table = 'dsam_form_submissions';

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $fillable = [
        'form_template_id',
        'student_id',
        'academic_year_id',
        'submitted_by',
        'evaluated_by',
        'approved_by',
        'status',
        'current_step',
        'total_score',
        'max_possible_score',
        'score_percentage',
        'risk_level',
        'draft_data',
        'submission_notes',
        'rejection_reason',
        'submitted_at',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'draft_data'         => 'array',
            'current_step'       => 'integer',
            'total_score'        => 'decimal:4',
            'max_possible_score' => 'decimal:4',
            'score_percentage'   => 'decimal:4',
            'submitted_at'       => 'datetime',
            'approved_at'        => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $submission): void {
            if (blank($submission->uuid)) {
                $submission->uuid = (string) Str::uuid();
            }
        });
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByRisk($query, string $level)
    {
        return $query->where('risk_level', $level);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePendingReview($query)
    {
        return $query->whereIn('status', ['submitted', 'under_review']);
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function formTemplate(): BelongsTo
    {
        return $this->belongsTo(FormTemplate::class, 'form_template_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(PreschoolStudent::class, 'student_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by', 'id');
    }

    public function evaluatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by', 'id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by', 'id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class, 'submission_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(SubmissionScore::class, 'submission_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(SubmissionApproval::class, 'submission_id')->orderBy('created_at');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isDraft(): bool
    {
        return in_array($this->status, ['draft', 'in_progress']);
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function canBeEdited(): bool
    {
        return in_array($this->status, ['draft', 'in_progress', 'rejected']);
    }

    public function riskColor(): string
    {
        return match ($this->risk_level) {
            'low'      => '#16a34a',
            'medium'   => '#d97706',
            'high'     => '#ea580c',
            'critical' => '#dc2626',
            default    => '#94a3b8',
        };
    }
}
