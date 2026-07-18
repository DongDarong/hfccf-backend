<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'division_id',
        'playing_style_id',
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
            'division_id' => 'integer',
            'playing_style_id' => 'integer',
        ];
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_user_id', 'id');
    }

    public function divisionRelation(): BelongsTo
    {
        return $this->belongsTo(SportDivision::class, 'division_id');
    }

    public function playingStyle(): BelongsTo
    {
        return $this->belongsTo(SportPlayingStyle::class, 'playing_style_id');
    }

    public function coachAssignments(): HasMany
    {
        return $this->hasMany(CoachTeamAssignment::class, 'team_id');
    }

    public function activeCoachAssignment(): HasOne
    {
        return $this->hasOne(CoachTeamAssignment::class, 'team_id')->where('status', 'active');
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

    public function matchSquads(): HasMany
    {
        return $this->hasMany(SportMatchSquad::class, 'team_id');
    }

    public function trainingSessions(): HasMany
    {
        return $this->hasMany(SportTrainingSession::class, 'team_id');
    }
}
