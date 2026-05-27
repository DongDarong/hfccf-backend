<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreschoolAcademicTerm extends Model
{
    protected $fillable = [
        'academic_year_id',
        'code',
        'name',
        'start_date',
        'end_date',
        'status',
        'is_current',
        'sort_order',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(PreschoolAcademicYear::class, 'academic_year_id');
    }
}
