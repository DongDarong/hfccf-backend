<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreschoolReportSnapshot extends Model
{
    protected $fillable = [
        'snapshot_type',
        'student_id',
        'class_id',
        'academic_year_id',
        'term_id',
        'report_period_id',
        'generated_by',
        'lifecycle_state',
        'snapshot_version',
        'snapshot_payload',
        'generated_at',
        'locked_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_payload' => 'array',
            'generated_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(PreschoolStudent::class, 'student_id');
    }

    public function preschoolClass(): BelongsTo
    {
        return $this->belongsTo(PreschoolClass::class, 'class_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(PreschoolAcademicYear::class, 'academic_year_id');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(PreschoolAcademicTerm::class, 'term_id');
    }

    public function reportPeriod(): BelongsTo
    {
        return $this->belongsTo(PreschoolReportPeriod::class, 'report_period_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by', 'id');
    }
}
