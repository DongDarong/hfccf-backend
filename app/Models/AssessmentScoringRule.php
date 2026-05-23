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

    public function getFormTemplateIdAttribute(): int
    {
        return (int) $this->template_id;
    }

    public function getQuestionIdAttribute(): ?int
    {
        return $this->scope === 'question' ? (int) $this->scope_id : null;
    }

    public function setQuestionIdAttribute(mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $this->attributes['scope'] = 'question';
        $this->attributes['scope_id'] = $value;
    }

    public function getWeightAttribute(): mixed
    {
        return data_get($this->settings, 'weight');
    }

    public function setWeightAttribute(mixed $value): void
    {
        $settings = $this->settings ?? [];
        $settings['weight'] = $value;
        $this->attributes['settings'] = json_encode($settings);
    }
}
