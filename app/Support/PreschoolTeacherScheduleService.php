<?php

namespace App\Support;

use App\Models\PreschoolClass;
use App\Models\PreschoolScheduleEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PreschoolTeacherScheduleService
{
    /**
     * Teacher-visible timetable reads stay separate from admin management so
     * access checks, sorting, and view-specific filtering remain easy to audit.
     */
    public function classSchedule(User $actor, PreschoolClass $class): Collection
    {
        $this->ensureCanViewClassSchedule($actor, $class);

        return $this->buildVisibleQuery()
            ->where('class_id', $class->id)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->orderBy('id')
            ->get();
    }

    public function teacherSchedule(User $actor, User $teacher): Collection
    {
        $this->ensureCanViewTeacherSchedule($actor, $teacher);

        return $this->buildVisibleQuery()
            ->where('teacher_user_id', $teacher->id)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->orderBy('id')
            ->get();
    }

    public function mySchedule(User $actor): Collection
    {
        return $this->buildVisibleQuery()
            ->where('teacher_user_id', $actor->id)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->orderBy('id')
            ->get();
    }

    public function classSummary(PreschoolClass $class): array
    {
        return [
            'id' => $class->id,
            'code' => $class->code,
            'name' => $class->name,
            'teacherUserId' => $class->teacher_user_id,
            'teacherName' => $class->teacher_display_name ?: trim(($class->teacher?->first_name ?? '').' '.($class->teacher?->last_name ?? '')),
            'room' => $class->room,
        ];
    }

    public function teacherSummary(User $teacher): array
    {
        return [
            'id' => $teacher->id,
            'name' => trim($teacher->first_name.' '.$teacher->last_name),
            'username' => $teacher->username,
            'email' => $teacher->email,
        ];
    }

    private function buildVisibleQuery(): Builder
    {
        return PreschoolScheduleEntry::query()
            ->with(['preschoolClass.teacher', 'teacher'])
            ->whereIn('status', [PreschoolScheduleStatus::ACTIVE, PreschoolScheduleStatus::INACTIVE]);
    }

    private function ensureCanViewTeacherSchedule(User $actor, User $teacher): void
    {
        if (in_array($actor->role_code, ['superadmin', 'adminpreschool'], true)) {
            return;
        }

        abort_unless($actor->role_code === 'teacher-preschool' && $actor->id === $teacher->id, 403, 'Forbidden.');
    }

    private function ensureCanViewClassSchedule(User $actor, PreschoolClass $class): void
    {
        if (in_array($actor->role_code, ['superadmin', 'adminpreschool'], true)) {
            return;
        }

        abort_unless(
            $actor->role_code === 'teacher-preschool' && $class->teacher_user_id === $actor->id,
            403,
            'Forbidden.',
        );
    }
}
