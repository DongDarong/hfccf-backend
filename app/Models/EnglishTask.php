<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EnglishTask extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'class_id',
        'assigned_by_user_id',
        'title',
        'description',
        'due_date',
        'task_status',
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    public function class(): BelongsTo
    {
        return $this->belongsTo(EnglishClass::class, 'class_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id', 'id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(EnglishTaskSubmission::class, 'task_id');
    }
}
