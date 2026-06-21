<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'id',
        'name',
        'email',
        'password',
        'role_code',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function createdAttendanceSettings(): HasMany
    {
        return $this->hasMany(PreschoolAttendanceSetting::class, 'created_by', 'id');
    }

    public function updatedAttendanceSettings(): HasMany
    {
        return $this->hasMany(PreschoolAttendanceSetting::class, 'updated_by', 'id');
    }
}
