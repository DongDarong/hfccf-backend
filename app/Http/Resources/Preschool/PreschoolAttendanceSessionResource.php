<?php

namespace App\Http\Resources\Preschool;

use App\Models\PreschoolAttendanceSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use App\Support\BusinessTimezone;

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
        $status = $this->status === PreschoolAttendanceSession::STATUS_COMPLETED ? PreschoolAttendanceSession::STATUS_CLOSED : $this->status;
        $now = BusinessTimezone::nowInBusinessTimezone()->setTimezone('UTC');
        $isWritable = $status === PreschoolAttendanceSession::STATUS_OPEN
            && $this->opens_at !== null && $this->closes_at !== null
            && $now->greaterThanOrEqualTo($this->opens_at) && $now->lessThan($this->closes_at);

        return [
            'id' => $this->id,
            'code' => $this->session_code ?: $this->session_key,
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
            'opensAt' => $this->opens_at?->toISOString(),
            'closesAt' => $this->closes_at?->toISOString(),
            'businessTimezone' => BusinessTimezone::TIMEZONE,
            'serverNow' => $now->toISOString(),
            'teacherId' => $this->teacher_user_id ?: $this->preschoolClass?->teacher_user_id,
            'teacherName' => $this->teacher?->name ?: $this->preschoolClass?->teacher?->name,
            'startTime' => $this->formatTime($this->start_time),
            'endTime' => $this->formatTime($this->end_time),
            'status' => $status,
            'legacyStatus' => $this->status === PreschoolAttendanceSession::STATUS_COMPLETED ? PreschoolAttendanceSession::STATUS_COMPLETED : null,
            'statusLabel' => $status,
            'availabilityState' => $status === 'cancelled' ? 'cancelled' : ($status === 'locked' ? 'locked' : ($status === 'closed' ? 'closed' : ($isWritable ? 'available' : (($this->closes_at && $now->greaterThanOrEqualTo($this->closes_at)) ? 'expired' : ($status === 'draft' ? 'draft' : 'upcoming'))))),
            'isWritable' => $isWritable,
            'writeBlockedReason' => $isWritable ? null : ($status !== 'open' ? 'SESSION_NOT_OPEN' : (($this->opens_at && $now->lessThan($this->opens_at)) ? 'SESSION_NOT_STARTED' : 'SESSION_WINDOW_CLOSED')),
            'isScheduled' => $status === 'scheduled',
            'isOpen' => $status === 'open',
            'isCompleted' => $status === 'closed',
            'isLocked' => $status === 'locked',
            'isCancelled' => $status === 'cancelled',
            'generatedFromSchedule' => (bool) $this->generated_from_schedule,
            'notes' => $this->notes,
            'studentCount' => $studentCount,
            'recordedStudents' => $recordCount,
            'missingStudents' => $missingCount,
            'completionRate' => $studentCount > 0 ? round(($recordCount / $studentCount) * 100, 2) : 0.0,
            'canRecord' => $isWritable,
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
