<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreschoolAutomationTask extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_OVERDUE = 'overdue';

    protected $fillable = [
        'task_type',
        'title',
        'description',
        'priority',
        'status',
        'assigned_to_user_id',
        'assigned_role',
        'due_at',
        'source_type',
        'source_id',
        'preschool_student_id',
        'preschool_class_id',
        'action_route',
        'action_params',
        'created_by',
        'completed_by',
        'completed_at',
        'cancelled_by',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'action_params' => 'array',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function assignedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id', 'id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by', 'id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by', 'id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(PreschoolStudent::class, 'preschool_student_id');
    }

    public function preschoolClass(): BelongsTo
    {
        return $this->belongsTo(PreschoolClass::class, 'preschool_class_id');
    }
}
