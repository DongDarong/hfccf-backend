<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SportMatchEvent extends Model
{
    protected $fillable = [
        'match_id',
        'team_id',
        'player_id',
        'event_type',
        'minute',
        'extra_time_minute',
        'metadata',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'minute' => 'integer',
            'extra_time_minute' => 'integer',
            'metadata' => 'array',
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

    public function player(): BelongsTo
    {
        return $this->belongsTo(SportPlayer::class, 'player_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'id');
    }
}

