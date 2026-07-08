<?php

namespace App\Http\Resources\Preschool;

use App\Models\PreschoolReportPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PreschoolReportPeriod */
class PreschoolReportPeriodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'periodLabel' => $this->period_label,
            'periodType' => $this->period_type,
            'academicYearId' => $this->academic_year_id,
            'academicYear' => $this->academicYear?->label,
            'academicYearCode' => $this->academicYear?->code,
            'termId' => $this->term_id,
            'termLabel' => $this->term?->name,
            'termCode' => $this->term?->code,
            'fromDate' => $this->from_date?->toDateString(),
            'toDate' => $this->to_date?->toDateString(),
            'status' => $this->status,
            'summarySnapshot' => $this->summary_snapshot,
            'reportSnapshot' => $this->report_snapshot,
            'lockedAt' => $this->locked_at?->toISOString(),
            'lockedByUserId' => $this->locked_by,
            'lockedByName' => trim(($this->lockedBy?->first_name ?? '').' '.($this->lockedBy?->last_name ?? '')),
            'finalizedAt' => $this->finalized_at?->toISOString(),
            'finalizedByUserId' => $this->finalized_by,
            'finalizedByName' => trim(($this->finalizedBy?->first_name ?? '').' '.($this->finalizedBy?->last_name ?? '')),
            'archivedAt' => $this->archived_at?->toISOString(),
            'archivedByUserId' => $this->archived_by,
            'archivedByName' => trim(($this->archivedBy?->first_name ?? '').' '.($this->archivedBy?->last_name ?? '')),
            'notes' => $this->notes,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'isDraft' => $this->status === 'draft',
            'isActive' => $this->status === 'active',
            'isFinalized' => $this->status === 'finalized',
            'isLocked' => $this->status === 'locked',
            'isArchived' => $this->status === 'archived',
        ];
    }
}
