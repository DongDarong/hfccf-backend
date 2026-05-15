<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScholarshipStudent extends Model
{
    use SoftDeletes;

    protected $table = 'scholarship_students';

    protected $fillable = [
        'student_code',
        'first_name',
        'last_name',
        'gender',
        'date_of_birth',
        'phone',
        'email',
        'school_name',
        'grade_level',
        'guardian_name',
        'guardian_phone',
        'address',
        'status',
        'notes',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function applications(): HasMany
    {
        return $this->hasMany(ScholarshipApplication::class, 'student_id');
    }
}
