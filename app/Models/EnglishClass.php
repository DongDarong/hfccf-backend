<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EnglishClass extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'class_code',
        'name',
        'level',
        'teacher_user_id',
        'schedule',
        'room',
        'status',
        'description',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_user_id', 'id');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(EnglishStudent::class, 'english_class_students', 'class_id', 'student_id')
            ->withPivot(['enrolled_at', 'status'])
            ->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(EnglishTask::class, 'class_id');
    }
}
