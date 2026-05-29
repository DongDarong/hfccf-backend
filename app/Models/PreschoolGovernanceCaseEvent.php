<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreschoolGovernanceCaseEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'governance_case_id',
        'event_type',
        'actor_user_id',
        'actor_role',
        'previous_status',
        'new_status',
        'note',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function governanceCase(): BelongsTo
    {
        return $this->belongsTo(PreschoolGovernanceCase::class, 'governance_case_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id', 'id');
    }
}
