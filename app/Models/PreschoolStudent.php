<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PreschoolStudent extends Model
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
        'address',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(
            PreschoolClass::class,
            'preschool_class_students',
            'student_id',
            'class_id',
        )->withPivot(['enrolled_at', 'status'])->withTimestamps();
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(PreschoolAttendanceRecord::class, 'student_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PreschoolPayment::class, 'student_id');
    }
}
