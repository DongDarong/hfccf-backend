<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreschoolGuardianCommunication extends Model
{
    protected $fillable = [
        'student_id',
        'guardian_id',
        'source_type',
        'source_id',
        'communication_type',
        'channel',
        'subject',
        'message',
        'severity',
        'status',
        'sent_at',
        'acknowledged_at',
        'failed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(PreschoolStudent::class, 'student_id');
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(PreschoolGuardian::class, 'guardian_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
}
