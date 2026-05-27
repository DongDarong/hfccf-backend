<?php

namespace App\Support;

use App\Models\PreschoolScheduleEntry;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class PreschoolScheduleService
{
    /**
     * Admin-managed timetable writes stay in one service so conflicts, field
     * normalization, and audit-ready metadata are handled consistently.
     */
    public function __construct(
        private readonly PreschoolScheduleConflictService $conflictService,
    ) {}

    public function paginateSchedules(User $actor, array $filters = []): LengthAwarePaginator
    {
        $this->ensureAdmin($actor);

        $page = max((int) ($filters['page'] ?? 1), 1);
        $perPage = min(max((int) ($filters['per_page'] ?? 10), 1), 100);
        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $classId = $this->nullableInt($filters['class_id'] ?? null);
        $teacherUserId = trim((string) ($filters['teacher_user_id'] ?? ''));
        $dayOfWeek = $this->nullableInt($filters['day_of_week'] ?? null);

        $query = PreschoolScheduleEntry::query()->with(['preschoolClass.teacher', 'teacher']);

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->where('activity_label', 'like', $like)
                    ->orWhere('room', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhereHas('preschoolClass', static function (Builder $classQuery) use ($like): void {
                        $classQuery->where('code', 'like', $like)
                            ->orWhere('name', 'like', $like);
                    })
                    ->orWhereHas('teacher', static function (Builder $teacherQuery) use ($like): void {
                        $teacherQuery->where('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhere('username', 'like', $like);
                    });
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($classId !== null) {
            $query->where('class_id', $classId);
        }

        if ($teacherUserId !== '') {
            $query->where('teacher_user_id', $teacherUserId);
        }

        if ($dayOfWeek !== null) {
            $query->where('day_of_week', $dayOfWeek);
        }

        return $query
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function createSchedule(User $actor, array $data): PreschoolScheduleEntry
    {
        $this->ensureAdmin($actor);

        return PreschoolScheduleEntry::query()->create(
            $this->normalizePayload($data) + [
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ],
        );
    }

    public function updateSchedule(User $actor, PreschoolScheduleEntry $schedule, array $data): PreschoolScheduleEntry
    {
        $this->ensureAdmin($actor);

        $schedule->fill($this->normalizePayload($data, $schedule) + [
            'updated_by_user_id' => $actor->id,
        ]);
        $schedule->save();

        return $schedule->refresh()->load(['preschoolClass.teacher', 'teacher']);
    }

    public function archiveSchedule(User $actor, PreschoolScheduleEntry $schedule): PreschoolScheduleEntry
    {
        $this->ensureAdmin($actor);

        $schedule->status = PreschoolScheduleStatus::ARCHIVED;
        $schedule->updated_by_user_id = $actor->id;
        $schedule->save();

        return $schedule->refresh()->load(['preschoolClass.teacher', 'teacher']);
    }

    public function normalizePayload(array $data, ?PreschoolScheduleEntry $schedule = null): array
    {
        return [
            'class_id' => $this->nullableInt($data['class_id'] ?? $schedule?->class_id),
            'teacher_user_id' => $this->nullableString($data['teacher_user_id'] ?? $schedule?->teacher_user_id),
            'day_of_week' => (int) ($data['day_of_week'] ?? $schedule?->day_of_week ?? PreschoolScheduleDay::MONDAY),
            'start_time' => $this->normalizeTime($data['start_time'] ?? $schedule?->start_time),
            'end_time' => $this->normalizeTime($data['end_time'] ?? $schedule?->end_time),
            'room' => $this->nullableString($data['room'] ?? $schedule?->room),
            'activity_label' => trim((string) ($data['activity_label'] ?? $schedule?->activity_label ?? '')),
            'notes' => $this->nullableString($data['notes'] ?? $schedule?->notes),
            'status' => $this->normalizeStatus($data['status'] ?? $schedule?->status),
            'effective_from' => $this->normalizeDate($data['effective_from'] ?? $schedule?->effective_from),
            'effective_until' => $this->normalizeDate($data['effective_until'] ?? $schedule?->effective_until),
        ];
    }

    public function detectConflicts(?PreschoolScheduleEntry $schedule, array $data): array
    {
        return $this->conflictService->detectConflicts($schedule, $this->normalizePayload($data, $schedule));
    }

    private function ensureAdmin(User $actor): void
    {
        abort_unless(in_array($actor->role_code, ['superadmin', 'adminpreschool'], true), 403, 'Forbidden.');
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizeTime(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return Carbon::parse($value)->format('H:i');
    }

    private function normalizeDate(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return Carbon::parse($value)->toDateString();
    }

    private function normalizeStatus(mixed $value): string
    {
        $value = trim((string) $value);

        return in_array($value, PreschoolScheduleStatus::values(), true)
            ? $value
            : PreschoolScheduleStatus::ACTIVE;
    }
}
