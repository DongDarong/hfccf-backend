<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreschoolGovernanceCaseEvidence extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'governance_case_id',
        'evidence_type',
        'evidence_reference',
        'evidence_label',
        'evidence_description',
        'metadata',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function governanceCase(): BelongsTo
    {
        return $this->belongsTo(PreschoolGovernanceCase::class, 'governance_case_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
}
