<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PreschoolWorkflowInstance extends Model
{
    protected $fillable = [
        'workflow_definition_id',
        'source_type',
        'source_id',
        'source_label',
        'current_step_id',
        'status',
        'priority',
        'assigned_to_user_id',
        'assigned_role',
        'due_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'escalated_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'escalated_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(PreschoolWorkflowDefinition::class, 'workflow_definition_id');
    }

    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(PreschoolWorkflowStep::class, 'current_step_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id', 'id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(PreschoolWorkflowApproval::class, 'workflow_instance_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PreschoolWorkflowEvent::class, 'workflow_instance_id');
    }
}
