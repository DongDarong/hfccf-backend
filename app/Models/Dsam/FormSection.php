<?php

namespace App\Models\Dsam;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormSection extends Model
{
    protected $table = 'dsam_form_sections';

    protected $fillable = [
        'form_template_id',
        'title',
        'title_kh',
        'description',
        'description_kh',
        'order_index',
        'scoring_weight',
        'is_required',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'scoring_weight' => 'decimal:4',
            'is_required'    => 'boolean',
            'settings'       => 'array',
            'order_index'    => 'integer',
        ];
    }

    public function formTemplate(): BelongsTo
    {
        return $this->belongsTo(FormTemplate::class, 'form_template_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'form_section_id')
            ->whereNull('parent_question_id')   // top-level questions only; children fetched via question
            ->orderBy('order_index');
    }

    /** All questions including conditional children */
    public function allQuestions(): HasMany
    {
        return $this->hasMany(Question::class, 'form_section_id')->orderBy('order_index');
    }

    public function submissionScores(): HasMany
    {
        return $this->hasMany(SubmissionScore::class, 'form_section_id');
    }
}
