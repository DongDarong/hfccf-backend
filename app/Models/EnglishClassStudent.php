<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnglishClassStudent extends Model
{
    protected $fillable = [
        'class_id',
        'student_id',
        'enrolled_at',
        'status',
    ];

    public function class(): BelongsTo
    {
        return $this->belongsTo(EnglishClass::class, 'class_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(EnglishStudent::class, 'student_id');
    }
}
