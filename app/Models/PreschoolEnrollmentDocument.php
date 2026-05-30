<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreschoolEnrollmentDocument extends Model
{
    public const TYPES = [
        'birth_certificate',
        'family_book',
        'vaccination_card',
        'parent_id',
        'photo',
        'consent_form',
    ];

    protected $fillable = [
        'application_id',
        'document_type',
        'is_required',
        'is_received',
        'received_date',
        'file_path',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_received' => 'boolean',
            'received_date' => 'date',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(PreschoolEnrollmentApplication::class, 'application_id');
    }
}
