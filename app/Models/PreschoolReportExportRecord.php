<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreschoolReportExportRecord extends Model
{
    protected $fillable = [
        'actor_user_id',
        'actor_role',
        'export_type',
        'export_format',
        'export_source',
        'academic_year_id',
        'term_id',
        'report_period_id',
        'filters',
        'snapshot_ids',
        'record_count',
        'file_name',
        'checksum',
        'export_reason',
        'request_context',
        'exported_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'snapshot_ids' => 'array',
            'request_context' => 'array',
            'exported_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
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
}
