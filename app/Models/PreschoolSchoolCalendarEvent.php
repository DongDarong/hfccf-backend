<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PreschoolSchoolCalendarEvent extends Model
{
    use SoftDeletes;

    public const TYPE_HOLIDAY = 'holiday';
    public const TYPE_CLOSURE = 'closure';
    public const TYPE_TEACHER_TRAINING = 'teacher_training';
    public const TYPE_EXAMINATION = 'examination';
    public const TYPE_SPECIAL_EVENT = 'special_event';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    public const TYPES = [
        self::TYPE_HOLIDAY,
        self::TYPE_CLOSURE,
        self::TYPE_TEACHER_TRAINING,
        self::TYPE_EXAMINATION,
        self::TYPE_SPECIAL_EVENT,
    ];

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_ARCHIVED,
    ];

    protected $table = 'preschool_school_calendar_events';

    protected $fillable = [
        'academic_year_id',
        'title',
        'description',
        'type',
        'start_date',
        'end_date',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'deleted_at' => 'datetime',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(PreschoolAcademicYear::class, 'academic_year_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by', 'id');
    }
}
