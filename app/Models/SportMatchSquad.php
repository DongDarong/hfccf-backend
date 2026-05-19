<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SportMatchSquad extends Model
{
    protected $fillable = [
        'match_id',
        'team_id',
        'selected_by_user_id',
        'status',
        'locked_at',
        'submitted_at',
        'approved_by_user_id',
        'approved_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'locked_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(SportMatch::class, 'match_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(SportTeam::class, 'team_id');
    }

    public function selectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'selected_by_user_id', 'id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id', 'id');
    }

    public function players(): HasMany
    {
        return $this->hasMany(SportMatchSquadPlayer::class, 'squad_id');
    }
}
