<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PreschoolGuardian extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'full_name',
        'phone',
        'secondary_phone',
        'email',
        'address',
        'occupation',
        'national_id',
        'status',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    /**
     * Keep guardian records separate from student CRUD so contacts can be
     * reused across siblings without duplicating raw text fields.
     */
    public function studentGuardians(): HasMany
    {
        return $this->hasMany(PreschoolStudentGuardian::class, 'guardian_id');
    }

    public function activeStudentGuardians(): HasMany
    {
        return $this->studentGuardians()->where('status', 'active');
    }

    /**
     * Mirror the student side of the relationship so admin screens can move
     * across siblings without re-querying the pivot manually.
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(
            PreschoolStudent::class,
            'preschool_student_guardians',
            'guardian_id',
            'student_id',
        )->withPivot([
            'relationship_type',
            'is_primary',
            'can_pickup',
            'emergency_priority',
            'status',
            'starts_at',
            'ends_at',
            'notes',
        ])->withTimestamps();
    }
}
