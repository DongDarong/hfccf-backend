<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SportMatchEvent extends Model
{
    protected $fillable = [
        'tournament_id',
        'match_id',
        'team_id',
        'player_id',
        'assist_player_id',
        'player_in_id',
        'player_out_id',
        'event_type',
        'minute',
        'extra_time_minute',
        'stoppage_minute',
        'side',
        'description',
        'metadata',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'minute' => 'integer',
            'extra_time_minute' => 'integer',
            'stoppage_minute' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(SportTournament::class, 'tournament_id');
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

    public function assistPlayer(): BelongsTo
    {
        return $this->belongsTo(SportPlayer::class, 'assist_player_id');
    }

    public function playerIn(): BelongsTo
    {
        return $this->belongsTo(SportPlayer::class, 'player_in_id');
    }

    public function playerOut(): BelongsTo
    {
        return $this->belongsTo(SportPlayer::class, 'player_out_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'id');
    }
}
