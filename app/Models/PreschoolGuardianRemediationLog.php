<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreschoolGuardianRemediationLog extends Model
{
    protected $fillable = [
        'issue_type',
        'issue_key',
        'student_id',
        'guardian_id',
        'related_guardian_id',
        'relationship_id',
        'action',
        'before_snapshot',
        'after_snapshot',
        'notes',
        'performed_by_user_id',
        'performed_at',
    ];

    protected function casts(): array
    {
        return [
            'before_snapshot' => 'array',
            'after_snapshot' => 'array',
            'performed_at' => 'datetime',
        ];
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
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
