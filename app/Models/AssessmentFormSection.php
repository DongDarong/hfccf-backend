<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssessmentFormSection extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'template_id',
        'parent_id',
        'code',
        'title',
        'title_kh',
        'description',
        'description_kh',
        'sort_order',
        'is_repeatable',
        'max_repeats',
        'print_visible',
        'scoring_weight',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_repeatable'  => 'boolean',
            'print_visible'  => 'boolean',
            'scoring_weight' => 'decimal:2',
            'settings'       => 'array',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(AssessmentFormTemplate::class, 'template_id');
    }

    public function formTemplate(): BelongsTo
    {
        return $this->template();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(AssessmentFormSection::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(AssessmentFormSection::class, 'parent_id')->orderBy('sort_order');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(AssessmentQuestion::class, 'section_id')->orderBy('sort_order');
    }

    public function getFormTemplateIdAttribute(): int
    {
        return (int) $this->template_id;
    }

    public function getOrderAttribute(): int
    {
        return (int) $this->sort_order;
    }

    public function setOrderAttribute(mixed $value): void
    {
        $this->attributes['sort_order'] = is_numeric($value) ? (int) $value : 0;
    }
}
