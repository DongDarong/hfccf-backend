<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * PreschoolClass
 *
 * Represents a single classroom unit within the preschool. A class is taught
 * by one teacher and can have many enrolled students. The tuition_fee field
 * drives the auto-generated payment that is created when a student is enrolled
 * into this class via PreschoolEnrollmentService::enrollAsStudent().
 *
 * @property int         $id
 * @property string      $code
 * @property string      $name
 * @property string|null $teacher_user_id
 * @property string|null $teacher_display_name
 * @property string|null $level
 * @property string|null $schedule
 * @property int         $students_count
 * @property string|null $tuition_fee   Per-term fee charged to enrolled students
 * @property string      $status        active|inactive|archived
 * @property string|null $room
 * @property string|null $notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class PreschoolClass extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** @var list<string> Columns safe for mass-assignment */
    protected $fillable = [
        'code',
        'name',
        'teacher_user_id',
        'teacher_display_name',
        'class_level_id',
        'level',
        'schedule',
        'students_count',
        'tuition_fee',
        'status',
        'room',
        'notes',
    ];

    /**
     * @return array<string, string> Column cast definitions
     */
    protected function casts(): array
    {
        return [
            'students_count' => 'integer',
            'tuition_fee'    => 'decimal:2',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_user_id', 'id');
    }

    public function classLevel(): BelongsTo
    {
        return $this->belongsTo(PreschoolClassLevel::class, 'class_level_id');
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

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(PreschoolAttendanceSession::class, 'preschool_class_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PreschoolPayment::class, 'class_id');
    }

    /**
     * Teacher ownership history stays separate from the current teacher_user_id
     * column so Preschool admins can review reassignment changes without
     * treating teacher records as login accounts or overwriting the current
     * classroom owner.
     */
    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(PreschoolClassTeacherAssignment::class, 'class_id');
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
