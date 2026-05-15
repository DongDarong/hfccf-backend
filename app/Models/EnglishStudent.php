<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EnglishStudent extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'student_code',
        'first_name',
        'last_name',
        'gender',
        'date_of_birth',
        'guardian_name',
        'guardian_phone',
        'email',
        'phone',
        'address',
        'status',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
    ];

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(EnglishClass::class, 'english_class_students', 'student_id', 'class_id')
            ->withPivot(['enrolled_at', 'status'])
            ->withTimestamps();
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(EnglishTaskSubmission::class, 'student_id');
    }
}
