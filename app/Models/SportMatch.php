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
        'competition_type',
        'tournament_name',
        'venue',
        'scheduled_at',
        'started_at',
        'completed_at',
        'status',
        'current_period',
        'home_score',
        'away_score',
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
}
