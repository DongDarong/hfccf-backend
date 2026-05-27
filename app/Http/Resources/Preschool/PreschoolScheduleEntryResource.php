<?php

namespace App\Http\Resources\Preschool;

use App\Models\PreschoolScheduleEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/** @mixin PreschoolScheduleEntry */
class PreschoolScheduleEntryResource extends JsonResource
{
    /**
     * Keep timetable rows flat and predictable so the frontend can render
     * weekly grids without repeatedly dereferencing nested model objects.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'classId' => $this->class_id,
            'classCode' => $this->preschoolClass?->code,
            'className' => $this->preschoolClass?->name,
            'teacherUserId' => $this->teacher_user_id,
            'teacherName' => $this->teacherName(),
            'academicYearId' => $this->academic_year_id,
            'termId' => $this->term_id,
            'academicYear' => $this->academicYear?->label,
            'termLabel' => $this->term?->name,
            'dayOfWeek' => $this->day_of_week,
            'startTime' => $this->formatTime($this->start_time),
            'endTime' => $this->formatTime($this->end_time),
            'room' => $this->room,
            'activityLabel' => $this->activity_label,
            'notes' => $this->notes,
            'status' => $this->status,
            'isActive' => $this->status === 'active',
            'isArchived' => $this->status === 'archived',
            'effectiveFrom' => $this->effective_from?->toDateString(),
            'effectiveUntil' => $this->effective_until?->toDateString(),
            'createdByUserId' => $this->created_by_user_id,
            'updatedByUserId' => $this->updated_by_user_id,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }

    private function formatTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value)->format('H:i');
    }

    /**
     * Preserve a readable teacher label even when the schedule only stores a
     * class-level assignment and the teacher relation is intentionally null.
     */
    private function teacherName(): string
    {
        $teacherName = trim(($this->teacher?->first_name ?? '').' '.($this->teacher?->last_name ?? ''));

        if ($teacherName !== '') {
            return $teacherName;
        }

        return trim((string) ($this->preschoolClass?->teacher_display_name ?? ''));
    }
}
