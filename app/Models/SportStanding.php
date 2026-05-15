<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SportStanding extends Model
{
    protected $fillable = [
        'tournament_id',
        'team_id',
        'played',
        'wins',
        'draws',
        'losses',
        'goals_for',
        'goals_against',
        'goal_difference',
        'points',
        'rank_position',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(SportTournament::class, 'tournament_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(SportTeam::class, 'team_id');
    }
}
