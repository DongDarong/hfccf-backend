<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasApiTokens;
    use Notifiable;

    public $incrementing = false;

    protected $keyType = 'string';

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

    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            if (empty($user->getKey())) {
                $user->{$user->getKeyName()} = Str::random(16);
            }
        });
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
