<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class UserPermission extends Pivot
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'user_permissions';

    protected $fillable = [
        'user_id',
        'permission_code',
        'created_at',
    ];

    protected const UPDATED_AT = null;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'permission_code', 'code');
    }
}
