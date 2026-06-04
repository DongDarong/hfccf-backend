<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SportAttendanceRecord extends Model
{
    protected $fillable = [
        'attendance_type',
        'subject_key',
        'team_id',
        'player_id',
        'coach_user_id',
        'recorded_by_user_id',
        'attendance_date',
        'status',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(SportTeam::class, 'team_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(SportPlayer::class, 'player_id');
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_user_id', 'id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id', 'id');
    }
}
