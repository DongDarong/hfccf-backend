<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class PreschoolClassStudent extends Pivot
{
    public $incrementing = false;

    protected $table = 'preschool_class_students';

    protected $fillable = [
        'class_id',
        'student_id',
        'enrolled_at',
        'academic_year',
        'term_label',
        'academic_year_id',
        'term_id',
        'enrollment_status',
        'enrollment_started_at',
        'enrollment_ended_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
            'enrollment_started_at' => 'datetime',
            'enrollment_ended_at' => 'datetime',
        ];
    }
}
