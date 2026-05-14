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
        'status',
    ];
}
