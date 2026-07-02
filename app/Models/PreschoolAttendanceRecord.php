<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreschoolAttendanceRecord extends Model
{
    protected $fillable = [
        'class_id',
        'student_id',
        'attendance_session_id',
        'recorded_by_user_id',
        'attendance_date',
        'status',
        'note',
        'academic_year_id',
        'term_id',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
        ];
    }

    public function preschoolClass(): BelongsTo
    {
        return $this->belongsTo(PreschoolClass::class, 'class_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(PreschoolStudent::class, 'student_id');
    }

    public function attendanceSession(): BelongsTo
    {
        return $this->belongsTo(PreschoolAttendanceSession::class, 'attendance_session_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id', 'id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(PreschoolAcademicYear::class, 'academic_year_id');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(PreschoolAcademicTerm::class, 'term_id');
    }
}
