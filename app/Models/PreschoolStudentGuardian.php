<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PreschoolStudentGuardian extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'student_id',
        'guardian_id',
        'relationship_type',
        'is_primary',
        'can_pickup',
        'emergency_priority',
        'status',
        'starts_at',
        'ends_at',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'can_pickup' => 'boolean',
            'emergency_priority' => 'integer',
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(PreschoolStudent::class, 'student_id');
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(PreschoolGuardian::class, 'guardian_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id', 'id');
    }
}
