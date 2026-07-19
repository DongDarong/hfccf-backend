<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PreschoolAttendanceSession extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_LOCKED = 'locked';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SCHEDULED,
        self::STATUS_OPEN,
        self::STATUS_CLOSED,
        self::STATUS_COMPLETED,
        self::STATUS_LOCKED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'session_code',
        'preschool_class_id',
        'preschool_schedule_entry_id',
        'schedule_id',
        'teacher_user_id',
        'attendance_date',
        'start_time',
        'end_time',
        'opens_at',
        'closes_at',
        'status',
        'title',
        'generated_from_schedule',
        'notes',
        'session_key',
        'source_occurrence_key',
        'created_by',
        'created_by_user_id',
        'updated_by_user_id',
        'opened_by',
        'opened_by_user_id',
        'opened_at',
        'completed_by',
        'completed_at',
        'locked_by',
        'locked_by_user_id',
        'locked_at',
        'closed_by',
        'closed_at',
        'reopened_by',
        'last_reopened_by_user_id',
        'reopened_at',
        'last_reopened_at',
        'cancelled_by',
        'cancelled_by_user_id',
        'cancelled_at',
        'cancellation_reason',
        'closed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'generated_from_schedule' => 'boolean',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'opened_at' => 'datetime',
            'completed_at' => 'datetime',
            'locked_at' => 'datetime',
            'closed_at' => 'datetime',
            'reopened_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function preschoolClass(): BelongsTo
    {
        return $this->belongsTo(PreschoolClass::class, 'preschool_class_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(PreschoolScheduleEntry::class, 'schedule_id');
    }

    public function sourceSchedule(): BelongsTo
    {
        return $this->belongsTo(PreschoolScheduleEntry::class, 'preschool_schedule_entry_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_user_id', 'id');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(PreschoolAttendanceRecord::class, 'attendance_session_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by', 'id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by', 'id');
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by', 'id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by', 'id');
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by', 'id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by', 'id');
    }
}
