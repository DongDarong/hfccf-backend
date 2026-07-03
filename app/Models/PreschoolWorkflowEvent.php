<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreschoolWorkflowEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'workflow_instance_id',
        'event_type',
        'title',
        'description',
        'actor_user_id',
        'from_status',
        'to_status',
        'from_step_id',
        'to_step_id',
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

    public function instance(): BelongsTo
    {
        return $this->belongsTo(PreschoolWorkflowInstance::class, 'workflow_instance_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id', 'id');
    }

    public function fromStep(): BelongsTo
    {
        return $this->belongsTo(PreschoolWorkflowStep::class, 'from_step_id');
    }

    public function toStep(): BelongsTo
    {
        return $this->belongsTo(PreschoolWorkflowStep::class, 'to_step_id');
    }
}
