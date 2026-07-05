<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PreschoolWorkflowDefinition extends Model
{
    protected $fillable = [
        'key',
        'name',
        'description',
        'domain',
        'is_active',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(PreschoolWorkflowStep::class, 'workflow_definition_id')->orderBy('sort_order');
    }

    public function instances(): HasMany
    {
        return $this->hasMany(PreschoolWorkflowInstance::class, 'workflow_definition_id');
    }
}
