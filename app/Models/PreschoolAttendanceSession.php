<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PreschoolAttendanceSession extends Model
{
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_OPEN = 'open';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_LOCKED = 'locked';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_SCHEDULED,
        self::STATUS_OPEN,
        self::STATUS_COMPLETED,
        self::STATUS_LOCKED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'preschool_class_id',
        'schedule_id',
        'attendance_date',
        'start_time',
        'end_time',
        'status',
        'generated_from_schedule',
        'notes',
        'session_key',
        'created_by',
        'opened_by',
        'opened_at',
        'completed_by',
        'completed_at',
        'locked_by',
        'locked_at',
        'closed_by',
        'closed_at',
        'reopened_by',
        'reopened_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'generated_from_schedule' => 'boolean',
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
