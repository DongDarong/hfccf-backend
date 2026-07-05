<?php

namespace App\Http\Resources\Preschool;

use App\Models\PreschoolAttendanceRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PreschoolAttendanceRecord */
class PreschoolAttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'classId' => $this->class_id,
            'className' => $this->preschoolClass?->name,
            'studentId' => $this->student_id,
            'studentName' => trim(($this->student?->first_name ?? '').' '.($this->student?->last_name ?? '')),
            'attendanceSessionId' => $this->attendance_session_id,
            'attendanceSessionStatus' => $this->attendanceSession?->status,
            'attendanceSessionDate' => $this->attendanceSession?->attendance_date?->toDateString(),
            'attendanceSessionStartTime' => $this->attendanceSession?->start_time,
            'attendanceSessionEndTime' => $this->attendanceSession?->end_time,
            'attendanceSessionGeneratedFromSchedule' => (bool) ($this->attendanceSession?->generated_from_schedule ?? false),
            'attendanceSessionNotes' => $this->attendanceSession?->notes,
            'recordedByUserId' => $this->recorded_by_user_id,
            'recordedByName' => $this->recordedBy?->name,
            'academicYearId' => $this->academic_year_id,
            'termId' => $this->term_id,
            'academicYear' => $this->academicYear?->label,
            'termLabel' => $this->term?->name,
            'attendanceDate' => $this->attendance_date?->toDateString(),
            'status' => $this->status,
            'note' => $this->note,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
