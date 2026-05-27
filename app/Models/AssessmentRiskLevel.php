<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentRiskLevel extends Model
{
    protected $fillable = [
        'template_id',
        'label',
        'label_kh',
        'key',
        'min_score',
        'max_score',
        'color_code',
        'sort_order',
        'description',
        'recommendations',
    ];

    protected function casts(): array
    {
        return [
            'min_score' => 'decimal:2',
            'max_score' => 'decimal:2',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(AssessmentFormTemplate::class, 'template_id');
    }
}
