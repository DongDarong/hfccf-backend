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

    public function getAnswerValueAttribute(): mixed
    {
        if ($this->answer_options !== null) {
            return $this->answer_options;
        }

        if ($this->answer_matrix !== null) {
            return $this->answer_matrix;
        }

        if ($this->answer_file !== null) {
            return $this->answer_file;
        }

        if ($this->answer_gps !== null) {
            return $this->answer_gps;
        }

        if ($this->answer_text !== null) {
            return $this->answer_text;
        }

        if ($this->answer_number !== null) {
            return $this->answer_number;
        }

        if ($this->answer_date !== null) {
            return $this->answer_date?->toDateString();
        }

        return null;
    }

    public function setAnswerValueAttribute(mixed $value): void
    {
        if (is_array($value)) {
            $this->attributes['answer_options'] = json_encode($value);
            return;
        }

        if ($value instanceof \DateTimeInterface) {
            $this->attributes['answer_date'] = $value->format('Y-m-d');
            return;
        }

        if (is_numeric($value)) {
            $this->attributes['answer_number'] = $value;
            return;
        }

        $this->attributes['answer_text'] = is_bool($value) ? ($value ? '1' : '0') : $value;
    }
}
