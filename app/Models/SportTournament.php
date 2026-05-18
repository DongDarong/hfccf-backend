<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SportTournament extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tournament_code',
        'slug',
        'name',
        'season',
        'tournament_type',
        'status',
        'visibility',
        'registration_open_at',
        'registration_close_at',
        'starts_at',
        'ends_at',
        'description',
        'logo_path',
        'banner_path',
        'location',
        'organizer',
        'rules',
        'settings',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'registration_open_at' => 'datetime',
            'registration_close_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'rules' => 'array',
            'settings' => 'array',
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

    public function groups(): HasMany
    {
        return $this->hasMany(SportTournamentGroup::class, 'tournament_id');
    }

    public function knockoutRounds(): HasMany
    {
        return $this->hasMany(SportTournamentKnockoutRound::class, 'tournament_id');
    }

    public function matchEvents(): HasMany
    {
        return $this->hasMany(SportMatchEvent::class, 'tournament_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'id');
    }
}
