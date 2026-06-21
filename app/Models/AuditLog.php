<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = null;

    protected $fillable = [
        'event_type',
        'module',
        'entity_type',
        'entity_id',
        'actor_id',
        'actor_name',
        'actor_role',
        'action',
        'before_state',
        'after_state',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'before_state' => 'array',
        'after_state' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public $timestamps = false;
}
