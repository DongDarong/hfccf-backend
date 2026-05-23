<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentSubmissionScore extends Model
{
    protected $fillable = [
        'submission_id',
        'scope',
        'scope_id',
        'raw_score',
        'max_score',
        'weighted_score',
        'percentage',
        'risk_level_id',
        'override_score',
        'override_by',
        'override_note',
    ];

    protected function casts(): array
    {
        return [
            'raw_score'      => 'decimal:2',
            'max_score'      => 'decimal:2',
            'weighted_score' => 'decimal:2',
            'percentage'     => 'decimal:2',
            'override_score' => 'decimal:2',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(AssessmentSubmission::class, 'submission_id');
    }

    public function riskLevel(): BelongsTo
    {
        return $this->belongsTo(AssessmentRiskLevel::class, 'risk_level_id');
    }

    public function overrider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'override_by', 'id');
    }
}
