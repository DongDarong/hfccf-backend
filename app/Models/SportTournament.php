<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SportTournament extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tournament_code',
        'name',
        'season',
        'tournament_type',
        'status',
        'starts_at',
        'ends_at',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(SportTeam::class, 'sport_tournament_teams', 'tournament_id', 'team_id')
            ->withTimestamps()
            ->withPivot(['joined_at']);
    }

    public function standings(): HasMany
    {
        return $this->hasMany(SportStanding::class, 'tournament_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(SportMatch::class, 'tournament_id');
    }
}
