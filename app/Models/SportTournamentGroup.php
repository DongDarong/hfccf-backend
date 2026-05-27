<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SportTournamentGroup extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tournament_id',
        'name',
        'code',
        'position',
        'qualification_slots',
        'status',
        'finalized_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'qualification_slots' => 'integer',
            'finalized_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(SportTournament::class, 'tournament_id');
    }

    public function groupTeams(): HasMany
    {
        return $this->hasMany(SportTournamentGroupTeam::class, 'group_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(SportMatch::class, 'group_id');
    }
}
