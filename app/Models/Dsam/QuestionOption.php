<?php

namespace App\Models\Dsam;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionOption extends Model
{
    public $timestamps = false;

    protected $table = 'dsam_question_options';

    protected $fillable = [
        'question_id',
        'label',
        'label_kh',
        'value',
        'score_value',
        'order_index',
        'is_default',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'score_value' => 'decimal:2',
            'order_index' => 'integer',
            'is_default'  => 'boolean',
            'config'      => 'array',
            'created_at'  => 'datetime',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id');
    }

    /** Questions that are conditionally shown when this option is selected */
    public function triggeredQuestions(): HasMany
    {
        return $this->hasMany(Question::class, 'trigger_option_id');
    }
}
