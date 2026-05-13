<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait HasAuditFields
{
    protected static function bootHasAuditFields(): void
    {
        static::creating(function ($model) {
            if (! $model->created_by && Auth::check()) {
                $model->created_by = Auth::id();
            }
            if (! $model->updated_by && Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });

        static::updating(function ($model) {
            if (! $model->isDirty('updated_by') && Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
