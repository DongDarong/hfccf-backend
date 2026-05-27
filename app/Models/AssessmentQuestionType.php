<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentQuestionType extends Model
{
    protected $fillable = [
        'key',
        'label',
        'label_kh',
        'renderer',
        'has_options',
        'has_scoring',
        'has_matrix',
        'is_file',
        'settings_schema',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'has_options'     => 'boolean',
            'has_scoring'     => 'boolean',
            'has_matrix'      => 'boolean',
            'is_file'         => 'boolean',
            'is_active'       => 'boolean',
            'settings_schema' => 'array',
        ];
    }

    public function questions(): HasMany
    {
        return $this->hasMany(AssessmentQuestion::class, 'question_type_id');
    }
}
