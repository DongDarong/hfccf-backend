<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreschoolStudentHealthAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'student_id',
        'actor_user_id',
        'action',
        'entity_type',
        'entity_id',
        'severity',
        'visibility',
        'before_state',
        'after_state',
        'message',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'before_state' => 'array',
            'after_state' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(PreschoolStudent::class, 'student_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}