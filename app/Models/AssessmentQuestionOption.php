<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssessmentQuestionOption extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'question_id',
        'label',
        'label_kh',
        'value',
        'score_value',
        'risk_tag',
        'color_code',
        'sort_order',
        'is_other',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'score_value' => 'decimal:2',
            'sort_order'  => 'integer',
            'is_other'    => 'boolean',
            'settings'    => 'array',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(AssessmentQuestion::class, 'question_id');
    }
}
