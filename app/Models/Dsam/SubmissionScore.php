<?php

namespace App\Models\Dsam;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionScore extends Model
{
    protected $table = 'dsam_submission_scores';

    protected $fillable = [
        'submission_id',
        'form_section_id',
        'raw_score',
        'weighted_score',
        'max_score',
        'percentage',
    ];

    protected function casts(): array
    {
        return [
            'raw_score'      => 'decimal:4',
            'weighted_score' => 'decimal:4',
            'max_score'      => 'decimal:4',
            'percentage'     => 'decimal:4',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'submission_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(FormSection::class, 'form_section_id');
    }
}
