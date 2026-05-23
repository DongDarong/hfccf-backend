<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssessmentSubmission extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'template_id',
        'version_id',
        'student_id',
        'assessor_id',
        'reviewer_id',
        'approver_id',
        'status',
        'submitted_at',
        'reviewed_at',
        'approved_at',
        'rejected_at',
        'rejection_note',
        'location_data',
        'device_info',
        'ip_address',
        'total_score',
        'max_score',
        'score_percent',
        'risk_level_id',
        'risk_override',
        'risk_note',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at'  => 'datetime',
            'reviewed_at'   => 'datetime',
            'approved_at'   => 'datetime',
            'rejected_at'   => 'datetime',
            'location_data' => 'array',
            'device_info'   => 'array',
            'total_score'   => 'decimal:2',
            'max_score'     => 'decimal:2',
            'score_percent' => 'decimal:2',
            'risk_override' => 'boolean',
            'meta'          => 'array',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(AssessmentFormTemplate::class, 'template_id');
    }

    public function formTemplate(): BelongsTo
    {
        return $this->template();
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(AssessmentFormVersion::class, 'version_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(PreschoolStudent::class, 'student_id');
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessor_id', 'id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id', 'id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id', 'id');
    }

    public function riskLevel(): BelongsTo
    {
        return $this->belongsTo(AssessmentRiskLevel::class, 'risk_level_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AssessmentAnswer::class, 'submission_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(AssessmentSubmissionScore::class, 'submission_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(AssessmentSubmissionHistory::class, 'submission_id')->orderBy('created_at', 'desc');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AssessmentAttachment::class, 'submission_id');
    }

    public function getFormTemplateIdAttribute(): int
    {
        return (int) $this->template_id;
    }

    public function setFormTemplateIdAttribute(mixed $value): void
    {
        $this->attributes['template_id'] = $value;
    }

    public function getModuleAttribute(): ?string
    {
        return $this->template?->module;
    }

    public function getReviewNoteAttribute(): ?string
    {
        return $this->risk_note;
    }

    public function setReviewNoteAttribute(?string $value): void
    {
        $this->attributes['risk_note'] = $value;
    }

    public function getRejectionReasonAttribute(): ?string
    {
        return $this->rejection_note;
    }

    public function setRejectionReasonAttribute(?string $value): void
    {
        $this->attributes['rejection_note'] = $value;
    }

    public function getCompletedAtAttribute()
    {
        return $this->approved_at ?? $this->rejected_at ?? $this->reviewed_at;
    }
}
