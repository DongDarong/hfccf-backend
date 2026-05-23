<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentScoringRule extends Model
{
    protected $fillable = [
        'template_id',
        'scope',
        'scope_id',
        'rule_type',
        'formula',
        'max_score',
        'pass_score',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'max_score'  => 'decimal:2',
            'pass_score' => 'decimal:2',
            'settings'   => 'array',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(AssessmentFormTemplate::class, 'template_id');
    }
}
