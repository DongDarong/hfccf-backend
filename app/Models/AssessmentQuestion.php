<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssessmentQuestion extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'section_id',
        'template_id',
        'question_type_id',
        'parent_question_id',
        'code',
        'label',
        'label_kh',
        'help_text',
        'help_text_kh',
        'placeholder',
        'placeholder_kh',
        'sort_order',
        'is_required',
        'is_scored',
        'max_score',
        'scoring_weight',
        'print_visible',
        'validation_rules',
        'conditional_logic',
        'calculation_formula',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_required'       => 'boolean',
            'is_scored'         => 'boolean',
            'print_visible'     => 'boolean',
            'max_score'         => 'decimal:2',
            'scoring_weight'    => 'decimal:2',
            'validation_rules'  => 'array',
            'conditional_logic' => 'array',
            'settings'          => 'array',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(AssessmentFormSection::class, 'section_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(AssessmentFormTemplate::class, 'template_id');
    }

    public function questionType(): BelongsTo
    {
        return $this->belongsTo(AssessmentQuestionType::class, 'question_type_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(AssessmentQuestion::class, 'parent_question_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(AssessmentQuestion::class, 'parent_question_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(AssessmentQuestionOption::class, 'question_id')->orderBy('sort_order');
    }

    public function matrixRows(): HasMany
    {
        return $this->hasMany(AssessmentMatrixRow::class, 'question_id')->orderBy('sort_order');
    }
}
