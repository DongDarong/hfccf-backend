<?php

namespace App\Models\Dsam;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Answer extends Model
{
    protected $table = 'dsam_answers';

    protected $fillable = [
        'submission_id',
        'question_id',
        'text_value',
        'number_value',
        'date_value',
        'json_value',
        'file_path',
        'score_value',
    ];

    protected function casts(): array
    {
        return [
            'number_value' => 'decimal:4',
            'date_value'   => 'date',
            'json_value'   => 'array',
            'score_value'  => 'decimal:4',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'submission_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id');
    }

    /**
     * Return the display value regardless of which typed column holds the data.
     * Useful for review pages and exports.
     */
    public function displayValue(): mixed
    {
        return $this->text_value
            ?? $this->number_value
            ?? $this->date_value?->toDateString()
            ?? $this->json_value
            ?? $this->file_path
            ?? null;
    }
}
