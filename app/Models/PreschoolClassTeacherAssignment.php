<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreschoolClassTeacherAssignment extends Model
{
    protected $fillable = [
        'class_id',
        'teacher_user_id',
        'teacher_display_name',
        'status',
        'assigned_at',
        'academic_year',
        'term_label',
        'academic_year_id',
        'term_id',
        'ended_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(PreschoolClass::class, 'class_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_user_id', 'id');
    }
}
