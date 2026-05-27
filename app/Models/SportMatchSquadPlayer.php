<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SportMatchSquadPlayer extends Model
{
    protected $fillable = [
        'squad_id',
        'match_id',
        'team_id',
        'player_id',
        'player_name_snapshot',
        'jersey_number_snapshot',
        'position_snapshot',
        'role',
        'eligibility_status',
        'is_eligible',
        'reason',
        'selected_at',
    ];

    protected function casts(): array
    {
        return [
            'is_eligible' => 'boolean',
            'selected_at' => 'datetime',
        ];
    }

    public function squad(): BelongsTo
    {
        return $this->belongsTo(SportMatchSquad::class, 'squad_id');
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
}
