<?php

namespace App\Models\Dsam;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionType extends Model
{
    public $timestamps = false;

    protected $table = 'dsam_question_types';

    protected $fillable = [
        'name',
        'display_name',
        'display_name_kh',
        'icon',
        'has_options',
        'has_scoring',
        'config_schema',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'has_options'   => 'boolean',
            'has_scoring'   => 'boolean',
            'is_active'     => 'boolean',
            'config_schema' => 'array',
        ];
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'question_type_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
