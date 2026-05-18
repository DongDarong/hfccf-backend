<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SportTournamentKnockoutRound extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tournament_id',
        'name',
        'code',
        'position',
        'bracket_size',
        'status',
        'completed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'bracket_size' => 'integer',
            'completed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(SportTournament::class, 'tournament_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(SportMatch::class, 'knockout_round_id');
    }
}
