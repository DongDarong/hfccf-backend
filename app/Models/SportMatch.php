<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SportMatch extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'match_code',
        'home_team_id',
        'away_team_id',
        'tournament_id',
        'group_id',
        'knockout_round_id',
        'competition_type',
        'match_type',
        'tournament_name',
        'round_name',
        'matchday',
        'venue',
        'scheduled_at',
        'started_at',
        'completed_at',
        'status',
        'approval_status',
        'approved_by_user_id',
        'approved_at',
        'rejection_reason',
        'requested_by_role',
        'current_period',
        'home_score',
        'away_score',
        'extra_time_home_score',
        'extra_time_away_score',
        'penalty_home_score',
        'penalty_away_score',
        'winner_team_id',
        'metadata',
        'notes',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'home_score' => 'integer',
            'away_score' => 'integer',
            'extra_time_home_score' => 'integer',
            'extra_time_away_score' => 'integer',
            'penalty_home_score' => 'integer',
            'penalty_away_score' => 'integer',
            'matchday' => 'integer',
            'metadata' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(SportTeam::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(SportTeam::class, 'away_team_id');
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(SportTournament::class, 'tournament_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(SportTournamentGroup::class, 'group_id');
    }

    public function knockoutRound(): BelongsTo
    {
        return $this->belongsTo(SportTournamentKnockoutRound::class, 'knockout_round_id');
    }

    public function winnerTeam(): BelongsTo
    {
        return $this->belongsTo(SportTeam::class, 'winner_team_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SportMatchEvent::class, 'match_id')
            ->orderBy('minute')
            ->orderBy('extra_time_minute')
            ->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id', 'id');
    }
}
