<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreschoolScheduleEntry extends Model
{
    protected $fillable = [
        'class_id',
        'teacher_user_id',
        'day_of_week',
        'start_time',
        'end_time',
        'room',
        'activity_label',
        'notes',
        'status',
        'effective_from',
        'effective_until',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    /**
     * The timetable entry stays attached to the Preschool class so schedule
     * views can always resolve the class context without duplicating fields.
     */
    public function preschoolClass(): BelongsTo
    {
        return $this->belongsTo(PreschoolClass::class, 'class_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_user_id', 'id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id', 'id');
    }
}
