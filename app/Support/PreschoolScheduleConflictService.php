<?php

namespace App\Support;

use App\Models\PreschoolScheduleEntry;

class PreschoolScheduleConflictService
{
    /**
     * Conflict detection stays isolated so overlap rules can evolve without
     * leaking database logic into controllers or request validation.
     *
     * @return array<int, array<string, mixed>>
     */
    public function detectConflicts(?PreschoolScheduleEntry $schedule, array $payload): array
    {
        if (($payload['status'] ?? PreschoolScheduleStatus::ACTIVE) !== PreschoolScheduleStatus::ACTIVE) {
            return [];
        }

        $ignoreId = $schedule?->id;
        $conflicts = [];

        $classConflicts = $this->buildOverlapQuery($payload, $ignoreId)
            ->where('class_id', $payload['class_id'])
            ->get();

        foreach ($classConflicts as $conflict) {
            $conflicts[] = $this->buildConflictPayload('class', $conflict, 'Same class has an overlapping schedule.');
        }

        if (! empty($payload['teacher_user_id'])) {
            $teacherConflicts = $this->buildOverlapQuery($payload, $ignoreId)
                ->where('teacher_user_id', $payload['teacher_user_id'])
                ->get();

            foreach ($teacherConflicts as $conflict) {
                $conflicts[] = $this->buildConflictPayload('teacher', $conflict, 'Same teacher has an overlapping schedule.');
            }
        }

        if (! empty($payload['room'])) {
            $roomConflicts = $this->buildOverlapQuery($payload, $ignoreId)
                ->where('room', $payload['room'])
                ->get();

            foreach ($roomConflicts as $conflict) {
                $conflicts[] = $this->buildConflictPayload('room', $conflict, 'Same room has an overlapping schedule.');
            }
        }

        return $this->deduplicateConflicts($conflicts);
    }

    private function buildOverlapQuery(array $payload, ?int $ignoreId)
    {
        return PreschoolScheduleEntry::query()
            ->with(['preschoolClass', 'teacher'])
            ->where('status', PreschoolScheduleStatus::ACTIVE)
            ->where('day_of_week', $payload['day_of_week'])
            ->where('start_time', '<', $payload['end_time'])
            ->where('end_time', '>', $payload['start_time'])
            ->when($ignoreId !== null, static fn ($query) => $query->where('id', '!=', $ignoreId));
    }

    /**
     * The frontend only needs a small, stable payload to explain why a save
     * failed; we avoid returning the entire model to keep the response compact.
     */
    private function buildConflictPayload(string $type, PreschoolScheduleEntry $conflict, string $message): array
    {
        return [
            'type' => $type,
            'message' => $message,
            'schedule' => [
                'id' => $conflict->id,
                'classId' => $conflict->class_id,
                'className' => $conflict->preschoolClass?->name,
                'teacherUserId' => $conflict->teacher_user_id,
                'teacherName' => trim(($conflict->teacher?->first_name ?? '').' '.($conflict->teacher?->last_name ?? '')),
                'dayOfWeek' => $conflict->day_of_week,
                'startTime' => $conflict->start_time,
                'endTime' => $conflict->end_time,
                'room' => $conflict->room,
                'activityLabel' => $conflict->activity_label,
                'status' => $conflict->status,
            ],
        ];
    }

    /**
     * Preserve one conflict per axis so the UI can show a concise list instead
     * of repeating the same schedule row multiple times.
     *
     * @param  array<int, array<string, mixed>>  $conflicts
     * @return array<int, array<string, mixed>>
     */
    private function deduplicateConflicts(array $conflicts): array
    {
        $seen = [];
        $unique = [];

        foreach ($conflicts as $conflict) {
            $key = $conflict['type'].'-'.$conflict['schedule']['id'];

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $conflict;
        }

        return $unique;
    }
}
