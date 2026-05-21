<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreschoolGuardianPortalAccount extends Model
{
    protected $fillable = [
        'guardian_id',
        'user_id',
        'email',
        'status',
        'invited_by_user_id',
        'invited_at',
        'activated_at',
        'revoked_at',
        'last_login_at',
        'metadata',
    ];

    /**
     * Portal access must stay separate from the guardian record so invited
     * users can be activated or revoked without losing relationship history.
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'invited_at' => 'datetime',
            'activated_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(PreschoolGuardian::class, 'guardian_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id', 'id');
    }
}
