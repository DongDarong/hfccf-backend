<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssessmentAttachment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'submission_id',
        'question_id',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'disk',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(AssessmentSubmission::class, 'submission_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(AssessmentQuestion::class, 'question_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by', 'id');
    }
}
