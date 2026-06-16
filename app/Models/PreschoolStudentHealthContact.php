<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PreschoolStudentHealthContact extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'student_id',
        'name',
        'relationship',
        'phone',
        'secondary_phone',
        'priority',
        'is_primary',
        'receive_alerts',
        'status',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'is_primary' => 'boolean',
            'receive_alerts' => 'boolean',
        ];
    }

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
