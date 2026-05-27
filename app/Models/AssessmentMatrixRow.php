<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentMatrixRow extends Model
{
    protected $fillable = [
        'question_id',
        'label',
        'label_kh',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(AssessmentQuestion::class, 'question_id');
    }
}
