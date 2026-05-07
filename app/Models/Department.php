<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'display_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class, 'department_code', 'code');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'department_code', 'code');
    }
}
