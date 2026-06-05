<?php

namespace App\Models\Dsam;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionApproval extends Model
{
    // Append-only event log — no updates or deletes
    public $timestamps = false;

    protected $table = 'dsam_submission_approvals';

    protected $fillable = [
        'submission_id',
        'action',
        'actor_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'submission_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id', 'id');
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            'submit'          => 'Submitted',
            'send_for_review' => 'Sent for Review',
            'approve'         => 'Approved',
            'reject'          => 'Rejected',
            'reopen'          => 'Reopened',
            'archive'         => 'Archived',
            default           => ucfirst($this->action),
        };
    }
}
