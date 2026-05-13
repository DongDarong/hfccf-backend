<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PasswordResetOtp extends Model
{
    protected $fillable = [
        'user_id',
        'email',
        'otp_hash',
        'purpose',
        'channel',
        'status',
        'attempts',
        'max_attempts',
        'resend_count',
        'expires_at',
        'verified_at',
        'used_at',
        'last_sent_at',
        'request_ip',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'max_attempts' => 'integer',
            'resend_count' => 'integer',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'used_at' => 'datetime',
            'last_sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
