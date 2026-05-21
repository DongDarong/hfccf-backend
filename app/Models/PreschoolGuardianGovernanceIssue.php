<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreschoolGuardianGovernanceIssue extends Model
{
    protected $fillable = [
        'issue_type',
        'issue_key',
        'severity',
        'priority',
        'status',
        'student_id',
        'guardian_id',
        'relationship_id',
        'assigned_to_user_id',
        'detected_at',
        'acknowledged_at',
        'resolved_at',
        'dismissed_at',
        'recurrence_count',
        'latest_snapshot',
        'resolution_notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'latest_snapshot' => 'array',
            'metadata' => 'array',
            'detected_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
            'dismissed_at' => 'datetime',
            'recurrence_count' => 'integer',
        ];
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(PreschoolStudent::class, 'student_id');
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(PreschoolGuardian::class, 'guardian_id');
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(PreschoolStudentGuardian::class, 'relationship_id');
    }
}
