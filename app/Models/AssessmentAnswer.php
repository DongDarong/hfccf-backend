<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentAnswer extends Model
{
    protected $fillable = [
        'submission_id',
        'question_id',
        'question_code',
        'repeat_index',
        'answer_text',
        'answer_date',
        'answer_number',
        'answer_options',
        'answer_matrix',
        'answer_file',
        'answer_gps',
        'score_value',
        'is_skipped',
    ];

    protected function casts(): array
    {
        return [
            'answer_date'    => 'date',
            'answer_number'  => 'decimal:4',
            'answer_options' => 'array',
            'answer_matrix'  => 'array',
            'answer_gps'     => 'array',
            'score_value'    => 'decimal:2',
            'is_skipped'     => 'boolean',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(AssessmentSubmission::class, 'submission_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(AssessmentQuestion::class, 'question_id');
    }
}
