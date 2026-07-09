<?php

namespace App\Http\Resources\Preschool;

use App\Models\PreschoolAttendanceSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/** @mixin PreschoolAttendanceSession */
class PreschoolAttendanceSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $recordCount = $this->relationLoaded('attendanceRecords')
            ? $this->attendanceRecords->count()
            : (int) ($this->attendance_count ?? $this->attendanceRecords()->count());
        $studentCount = (int) ($this->preschoolClass?->students_count ?? 0);
        $missingCount = max($studentCount - $recordCount, 0);

        return [
            'id' => $this->id,
            'classId' => $this->preschool_class_id,
            'className' => $this->preschoolClass?->name,
            'classCode' => $this->preschoolClass?->code,
            'scheduleId' => $this->schedule_id,
            'schedule' => $this->schedule ? [
                'id' => $this->schedule->id,
                'dayOfWeek' => $this->schedule->day_of_week,
                'startTime' => $this->formatTime($this->schedule->start_time),
                'endTime' => $this->formatTime($this->schedule->end_time),
                'activityLabel' => $this->schedule->activity_label,
            ] : null,
            'attendanceDate' => $this->attendance_date?->toDateString(),
            'startTime' => $this->formatTime($this->start_time),
            'endTime' => $this->formatTime($this->end_time),
            'status' => $this->status,
            'statusLabel' => $this->status,
            'isScheduled' => $this->status === 'scheduled',
            'isOpen' => $this->status === 'open',
            'isCompleted' => $this->status === 'completed',
            'isLocked' => $this->status === 'locked',
            'isCancelled' => $this->status === 'cancelled',
            'generatedFromSchedule' => (bool) $this->generated_from_schedule,
            'notes' => $this->notes,
            'studentCount' => $studentCount,
            'recordedStudents' => $recordCount,
            'missingStudents' => $missingCount,
            'completionRate' => $studentCount > 0 ? round(($recordCount / $studentCount) * 100, 2) : 0.0,
            'canRecord' => ! in_array($this->status, ['completed', 'locked', 'cancelled'], true),
            'canViewDetails' => true,
            'createdByUserId' => $this->created_by,
            'createdByName' => $this->createdBy?->name,
            'openedByUserId' => $this->opened_by,
            'openedByName' => $this->openedBy?->name,
            'openedAt' => $this->opened_at?->toISOString(),
            'completedByUserId' => $this->completed_by,
            'completedByName' => $this->completedBy?->name,
            'completedAt' => $this->completed_at?->toISOString(),
            'lockedByUserId' => $this->locked_by,
            'lockedByName' => $this->lockedBy?->name,
            'lockedAt' => $this->locked_at?->toISOString(),
            'closedByUserId' => $this->closed_by,
            'closedByName' => $this->closedBy?->name,
            'closedAt' => $this->closed_at?->toISOString(),
            'reopenedByUserId' => $this->reopened_by,
            'reopenedByName' => $this->reopenedBy?->name,
            'reopenedAt' => $this->reopened_at?->toISOString(),
            'cancelledByUserId' => $this->cancelled_by,
            'cancelledByName' => $this->cancelledBy?->name,
            'cancelledAt' => $this->cancelled_at?->toISOString(),
            'cancellationReason' => $this->cancellation_reason,
            'attendanceCount' => $recordCount,
            'recordsCount' => $recordCount,
            'records' => $this->relationLoaded('attendanceRecords')
                ? PreschoolAttendanceResource::collection($this->attendanceRecords)->resolve($request)
                : [],
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
}
