<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnglishTaskSubmission extends Model
{
    protected $fillable = [
        'task_id',
        'student_id',
        'submission_text',
        'submitted_at',
        'submission_status',
        'score',
        'feedback',
        'reviewed_by_user_id',
        'reviewed_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'score' => 'decimal:2',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(EnglishTask::class, 'task_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(EnglishStudent::class, 'student_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id', 'id');
    }
}
