<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SportTeam extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'team_code',
        'name',
        'short_name',
        'coach_user_id',
        'coach_display_name',
        'division',
        'captain_name',
        'players_count',
        'matches_count',
        'wins',
        'draws',
        'losses',
        'points',
        'venue',
        'logo',
        'status',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'players_count' => 'integer',
            'matches_count' => 'integer',
            'wins' => 'integer',
            'draws' => 'integer',
            'losses' => 'integer',
            'points' => 'integer',
        ];
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_user_id', 'id');
    }

    public function players(): HasMany
    {
        return $this->hasMany(SportPlayer::class, 'team_id');
    }

    public function homeMatches(): HasMany
    {
        return $this->hasMany(SportMatch::class, 'home_team_id');
    }

    public function awayMatches(): HasMany
    {
        return $this->hasMany(SportMatch::class, 'away_team_id');
    }
}

