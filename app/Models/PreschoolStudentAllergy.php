<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PreschoolStudentAllergy extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'student_id',
        'allergy_name',
        'allergy_type',
        'severity',
        'reaction',
        'action_taken',
        'notes',
        'status',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(PreschoolStudent::class, 'student_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
