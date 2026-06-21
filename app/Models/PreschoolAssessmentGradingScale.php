<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class PreschoolAssessmentGradingScale extends Model
{
    protected $table = 'preschool_assessment_grading_scales';

    protected $fillable = [
        'name',
        'grade',
        'minimum_score',
        'maximum_score',
        'color',
        'sort_order',
        'is_passing',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'minimum_score' => 'decimal:2',
            'maximum_score' => 'decimal:2',
            'sort_order' => 'integer',
            'is_passing' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by', 'id');
    }
}
