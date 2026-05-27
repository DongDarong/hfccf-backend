<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PreschoolAcademicYear extends Model
{
    protected $fillable = [
        'code',
        'label',
        'start_date',
        'end_date',
        'status',
        'is_current',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
        ];
    }

    public function terms(): HasMany
    {
        return $this->hasMany(PreschoolAcademicTerm::class, 'academic_year_id');
    }
}
