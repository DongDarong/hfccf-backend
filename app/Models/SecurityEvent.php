<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SecurityEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_type',
        'severity',
        'user_id',
        'ip_address',
        'description',
        'metadata',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'metadata' => 'array',
        'resolved_at' => 'datetime',
    ];
}
