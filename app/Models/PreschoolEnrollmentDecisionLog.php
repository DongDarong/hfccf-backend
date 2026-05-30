<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreschoolEnrollmentDecisionLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'application_id',
        'action',
        'from_status',
        'to_status',
        'actor_user_id',
        'actor_role',
        'note',
        'context',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(PreschoolEnrollmentApplication::class, 'application_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
