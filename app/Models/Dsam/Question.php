<?php

namespace App\Models\Dsam;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Question extends Model
{
    use SoftDeletes;

    protected $table = 'dsam_questions';

    protected $fillable = [
        'uuid',
        'form_section_id',
        'question_type_id',
        'parent_question_id',
        'trigger_option_id',
        'label',
        'label_kh',
        'placeholder',
        'placeholder_kh',
        'help_text',
        'help_text_kh',
        'order_index',
        'is_required',
        'is_scored',
        'max_score',
        'validation_rules',
        'config',
        'scoring_config',
    ];

    protected function casts(): array
    {
        return [
            'is_required'      => 'boolean',
            'is_scored'        => 'boolean',
            'max_score'        => 'decimal:2',
            'order_index'      => 'integer',
            'validation_rules' => 'array',
            'config'           => 'array',
            'scoring_config'   => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $question): void {
            if (blank($question->uuid)) {
                $question->uuid = (string) Str::uuid();
            }
        });
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function section(): BelongsTo
    {
        return $this->belongsTo(FormSection::class, 'form_section_id');
    }

    public function questionType(): BelongsTo
    {
        return $this->belongsTo(QuestionType::class, 'question_type_id');
    }

    /** The parent question this conditional question depends on */
    public function parentQuestion(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'parent_question_id');
    }

    /** The option that must be selected to reveal this question */
    public function triggerOption(): BelongsTo
    {
        return $this->belongsTo(QuestionOption::class, 'trigger_option_id');
    }

    /** Conditional child questions that appear when this question's options are selected */
    public function conditionalChildren(): HasMany
    {
        return $this->hasMany(Question::class, 'parent_question_id')->orderBy('order_index');
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class, 'question_id')->orderBy('order_index');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class, 'question_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isConditional(): bool
    {
        return $this->parent_question_id !== null;
    }

    public function hasOptions(): bool
    {
        return $this->relationLoaded('questionType')
            ? (bool) $this->questionType->has_options
            : QuestionType::find($this->question_type_id)?->has_options ?? false;
    }
}
