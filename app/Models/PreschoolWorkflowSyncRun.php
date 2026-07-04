<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PreschoolWorkflowSyncRun extends Model
{
    protected $fillable = [
        'mode',
        'status',
        'definition_key',
        'source_type',
        'filters',
        'requested_limit',
        'batch_size',
        'eligible_count',
        'processed_count',
        'created_count',
        'existing_count',
        'skipped_count',
        'failed_count',
        'started_by_user_id',
        'started_at',
        'completed_at',
        'failure_message',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'requested_limit' => 'integer',
            'batch_size' => 'integer',
            'eligible_count' => 'integer',
            'processed_count' => 'integer',
            'created_count' => 'integer',
            'existing_count' => 'integer',
            'skipped_count' => 'integer',
            'failed_count' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by_user_id', 'id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PreschoolWorkflowSyncRunItem::class, 'sync_run_id');
    }
}
