<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreschoolReportPeriod extends Model
{
    protected $fillable = [
        'period_label',
        'period_type',
        'academic_year_id',
        'term_id',
        'from_date',
        'to_date',
        'status',
        'summary_snapshot',
        'report_snapshot',
        'locked_at',
        'locked_by',
        'finalized_at',
        'finalized_by',
        'archived_at',
        'archived_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
            'summary_snapshot' => 'array',
            'report_snapshot' => 'array',
            'locked_at' => 'datetime',
            'finalized_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(PreschoolAcademicYear::class, 'academic_year_id');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(PreschoolAcademicTerm::class, 'term_id');
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by', 'id');
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by', 'id');
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by', 'id');
    }
}
