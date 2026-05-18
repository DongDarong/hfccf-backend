<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SportPlayerTeamMembership extends Model
{
    protected $fillable = [
        'team_id',
        'player_id',
        'status',
        'joined_at',
        'left_at',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
        ];
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
