<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PreschoolHealthSeverityLevel extends Model
{
    use SoftDeletes;

    public const DEFAULT_CODES = ['low', 'medium', 'high', 'critical'];

    protected $table = 'preschool_health_severity_levels';

    protected $fillable = [
        'name',
        'code',
        'priority',
        'color',
        'requires_acknowledgment',
        'triggers_notification',
        'is_active',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'requires_acknowledgment' => 'boolean',
            'triggers_notification' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
