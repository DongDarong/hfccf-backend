<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreschoolLifecycleAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_user_id',
        'actor_role',
        'action_type',
        'entity_type',
        'entity_id',
        'academic_year_id',
        'term_id',
        'report_period_id',
        'previous_state',
        'new_state',
        'override_reason',
        'lock_code',
        'lock_reason',
        'request_context',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'previous_state' => 'array',
            'new_state' => 'array',
            'request_context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id', 'id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(PreschoolAcademicYear::class, 'academic_year_id');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(PreschoolAcademicTerm::class, 'term_id');
    }

    public function reportPeriod(): BelongsTo
    {
        return $this->belongsTo(PreschoolReportPeriod::class, 'report_period_id');
    }
}
