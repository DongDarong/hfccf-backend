<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class RolePermission extends Pivot
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'role_permissions';

    protected $fillable = [
        'role_code',
        'permission_code',
        'created_at',
    ];

    protected const UPDATED_AT = null;

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_code', 'code');
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'permission_code', 'code');
    }
}
