<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PreschoolClass extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'teacher_user_id',
        'teacher_display_name',
        'level',
        'schedule',
        'students_count',
        'status',
        'room',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'students_count' => 'integer',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_user_id', 'id');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(
            PreschoolStudent::class,
            'preschool_class_students',
            'class_id',
            'student_id',
        )->withPivot(['enrolled_at', 'status'])->withTimestamps();
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(PreschoolAttendanceRecord::class, 'class_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PreschoolPayment::class, 'class_id');
    }

    /**
     * Keep schedule rows attached to the class model so timetable pages can
     * reuse the same Preschool class context without duplicating fields.
     */
    public function scheduleEntries(): HasMany
    {
        return $this->hasMany(PreschoolScheduleEntry::class, 'class_id');
    }
}
