<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PreschoolWorkflowStep extends Model
{
    protected $fillable = [
        'workflow_definition_id',
        'key',
        'name',
        'sort_order',
        'step_type',
        'assigned_role',
        'sla_hours',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'sla_hours' => 'integer',
            'config' => 'array',
        ];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(PreschoolWorkflowDefinition::class, 'workflow_definition_id');
    }

    public function instances(): HasMany
    {
        return $this->hasMany(PreschoolWorkflowInstance::class, 'current_step_id');
    }
}
