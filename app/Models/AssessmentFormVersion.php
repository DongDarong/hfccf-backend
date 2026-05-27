<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentFormVersion extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'template_id',
        'version_number',
        'label',
        'snapshot',
        'change_summary',
        'published_at',
        'published_by',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'snapshot'     => 'array',
            'is_current'   => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(AssessmentFormTemplate::class, 'template_id');
    }
}
