<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentExportLog extends Model
{
    protected $fillable = [
        'uuid',
        'initiated_by',
        'export_type',
        'scope',
        'submission_ids',
        'print_template_id',
        'status',
        'file_path',
        'file_size',
        'error_message',
        'started_at',
        'completed_at',
        'expires_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'submission_ids' => 'array',
            'started_at'     => 'datetime',
            'completed_at'   => 'datetime',
            'expires_at'     => 'datetime',
            'meta'           => 'array',
        ];
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by', 'id');
    }

    public function printTemplate(): BelongsTo
    {
        return $this->belongsTo(AssessmentPrintTemplate::class, 'print_template_id');
    }
}
