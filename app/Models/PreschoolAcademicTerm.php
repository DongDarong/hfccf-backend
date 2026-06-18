<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreschoolAcademicTerm extends Model
{
    /**
     * The preschool academic lifecycle schema stores terms in the shared
     * `preschool_terms` table. Keep the model explicit so lifecycle queries
     * do not fall back to Laravel's default pluralization (`preschool_academic_terms`).
     */
    protected $table = 'preschool_terms';

    protected $fillable = [
        'academic_year_id',
        'code',
        'name',
        'description',
        'start_date',
        'end_date',
        'status',
        'is_current',
        'sort_order',
        'notes',
        'created_by',
        'updated_by',
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
