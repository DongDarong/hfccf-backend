<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SportTournamentGroupTeam extends Model
{
    protected $fillable = [
        'tournament_id',
        'group_id',
        'team_id',
        'seed',
        'pot',
        'position',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'seed' => 'integer',
            'pot' => 'integer',
            'position' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(SportTournament::class, 'tournament_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(SportTournamentGroup::class, 'group_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(SportTeam::class, 'team_id');
    }
}
