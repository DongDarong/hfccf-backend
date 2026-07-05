<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreschoolNotification extends Model
{
    public const STATUS_UNREAD = 'unread';
    public const STATUS_READ = 'read';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'notification_type',
        'title',
        'body',
        'severity',
        'status',
        'target_user_id',
        'target_role',
        'source_type',
        'source_id',
        'preschool_student_id',
        'preschool_class_id',
        'action_route',
        'action_params',
        'read_at',
        'archived_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'action_params' => 'array',
            'read_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id', 'id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
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
