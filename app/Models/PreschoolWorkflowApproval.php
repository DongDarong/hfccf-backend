<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreschoolWorkflowApproval extends Model
{
    protected $fillable = [
        'workflow_instance_id',
        'workflow_step_id',
        'requested_by_user_id',
        'requested_to_user_id',
        'requested_to_role',
        'status',
        'decision_notes',
        'decided_by_user_id',
        'decided_at',
        'due_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
            'due_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(PreschoolWorkflowInstance::class, 'workflow_instance_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(PreschoolWorkflowStep::class, 'workflow_step_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id', 'id');
    }

    public function requestedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_to_user_id', 'id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id', 'id');
    }
}
