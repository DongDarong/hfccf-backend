<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class PreschoolAssessmentSetting extends Model
{
    protected $table = 'preschool_assessment_settings';

    protected $fillable = [
        'passing_score',
        'grading_scale_type',
        'weighting_enabled',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'passing_score' => 'integer',
            'weighting_enabled' => 'boolean',
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
