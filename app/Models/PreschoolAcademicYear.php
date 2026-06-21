<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PreschoolAcademicYear extends Model
{
    protected $table = 'preschool_academic_years';

    protected $fillable = [
        'label',
        'code',
        'start_date',
        'end_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(PreschoolSchoolCalendarEvent::class, 'academic_year_id');
    }
}
