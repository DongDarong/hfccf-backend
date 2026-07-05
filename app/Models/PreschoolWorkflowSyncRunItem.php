<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreschoolWorkflowSyncRunItem extends Model
{
    protected $fillable = [
        'sync_run_id',
        'definition_key',
        'source_type',
        'source_id',
        'source_label',
        'result_status',
        'reason',
        'workflow_instance_id',
        'error_message',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PreschoolWorkflowSyncRun::class, 'sync_run_id');
    }

    public function workflowInstance(): BelongsTo
    {
        return $this->belongsTo(PreschoolWorkflowInstance::class, 'workflow_instance_id');
    }
}
