<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SportTrainingSession extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'session_code', 'team_id', 'coach_user_id', 'title', 'training_type',
        'focus', 'venue', 'starts_at', 'ends_at', 'intensity', 'status', 'notes',
        'created_by_user_id', 'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(SportTeam::class, 'team_id');
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_user_id', 'id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id', 'id');
    }
}
