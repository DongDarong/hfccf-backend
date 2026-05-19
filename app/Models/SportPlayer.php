<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class SportPlayer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'player_code',
        'first_name',
        'last_name',
        'jersey_number',
        'position',
        'team_id',
        'division',
        'gender',
        'age',
        'date_of_birth',
        'phone',
        'photo',
        'height_cm',
        'weight_kg',
        'preferred_foot',
        'blood_type',
        'village',
        'commune',
        'district',
        'province',
        'current_school',
        'grade_year',
        'primary_position',
        'registration_status',
        'approval_status',
        'roster_status',
        'disciplinary_status',
        'injury_status',
        'archived_at',
        'status_notes',
        'created_by_user_id',
        'approved_by_user_id',
        'approved_at',
        'rejection_reason',
        'matches_played',
        'goals_scored',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'jersey_number' => 'integer',
            'age' => 'integer',
            'date_of_birth' => 'date',
            'height_cm' => 'integer',
            'weight_kg' => 'decimal:2',
            'matches_played' => 'integer',
            'goals_scored' => 'integer',
            'approved_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(SportTeam::class, 'team_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id', 'id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SportMatchEvent::class, 'player_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(SportPlayerTeamMembership::class, 'player_id');
    }

    public function activeMembership(): HasOne
    {
        return $this->hasOne(SportPlayerTeamMembership::class, 'player_id')->where('status', 'active');
    }
}
