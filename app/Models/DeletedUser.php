<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeletedUser extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'original_id',
        'first_name',
        'last_name',
        'username',
        'email',
        'phone',
        'role_code',
        'department_code',
        'bio',
        'status',
        'avatar',
        'password',
        'email_verified_at',
        'last_login_at',
        'user_created_at',
        'user_updated_at',
        'deleted_at',
        'deleted_by',
        'original_data',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'user_created_at' => 'datetime',
            'user_updated_at' => 'datetime',
            'deleted_at' => 'datetime',
            'original_data' => 'array',
        ];
    }
}
