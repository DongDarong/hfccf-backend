<?php

namespace App\Support;

use App\Http\Resources\Preschool\PreschoolAttendanceSessionResource;
use App\Http\Resources\Preschool\PreschoolGuardianCommunicationResource;
use App\Http\Resources\Preschool\PreschoolScheduleEntryResource;
use App\Models\PreschoolAttendanceRecord;
use App\Models\PreschoolAttendanceSession;
use App\Models\PreschoolScheduleEntry;
use App\Models\User;
use App\Services\PreschoolGuardianCommunicationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class PreschoolScheduleSessionHistoryService
{
    public function paginateScheduleSessions(User $actor, PreschoolScheduleEntry $schedule, array $filters = []): LengthAwarePaginator
    {
        $query = $this->sessionQuery($actor, $schedule, $filters);
        $page = max((int) ($filters['page'] ?? 1), 1);
        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function todaySession(User $actor, PreschoolScheduleEntry $schedule): ?PreschoolAttendanceSession
    {
        return $this->sessionQuery($actor, $schedule, [
            'date_from' => now()->toDateString(),
            'date_to' => now()->toDateString(),
        ])->orderByDesc('attendance_date')
            ->orderByDesc('id')
            ->first();
    }

    public function history(User $actor, PreschoolScheduleEntry $schedule): array
    {
        [$dateFrom, $dateTo] = $this->defaultHistoryRange($schedule);

        $sessionFilters = [
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
        ];

        $recentSessions = $this->sessionQuery($actor, $schedule, $sessionFilters)
            ->orderByDesc('attendance_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $todaySession = $this->todaySession($actor, $schedule);

        return [
            'schedule' => $this->presentSchedule($schedule),
            'todaySession' => $todaySession,
            'recentSessions' => $recentSessions,
            'summary' => $this->summary($actor, $schedule, $sessionFilters),
            'alerts' => $this->relatedAlerts($actor, $schedule, $dateFrom, $dateTo),
            'guardianContacts' => $this->relatedGuardianContacts($actor, $schedule, $dateFrom, $dateTo),
        ];
    }

    public function summary(User $actor, PreschoolScheduleEntry $schedule, array $filters = []): array
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($schedule, $filters);

        $sessions = $this->sessionQuery($actor, $schedule, [
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
            'status' => $filters['status'] ?? null,
        ])->get();

        $sessionDates = $sessions
            ->pluck('attendance_date')
            ->filter()
            ->map(static fn ($date): string => $date instanceof Carbon ? $date->toDateString() : Carbon::parse((string) $date)->toDateString())
            ->unique()
            ->values();

        $expectedDates = $this->expectedSessionDates($schedule, $dateFrom, $dateTo);
        $attendanceRecords = $this->attendanceRecordsForSchedule($actor, $schedule, $dateFrom, $dateTo);

        $expectedCount = $expectedDates->count();
        $presentCount = $attendanceRecords->where('status', 'present')->count();
        $attendanceTotal = $attendanceRecords->count();

        return [
            'total' => $sessions->count(),
            'scheduled' => $sessions->where('status', PreschoolAttendanceSession::STATUS_SCHEDULED)->count(),
            'open' => $sessions->where('status', PreschoolAttendanceSession::STATUS_OPEN)->count(),
            'completed' => $sessions->where('status', PreschoolAttendanceSession::STATUS_COMPLETED)->count(),
            'locked' => $sessions->where('status', PreschoolAttendanceSession::STATUS_LOCKED)->count(),
            'cancelled' => $sessions->where('status', PreschoolAttendanceSession::STATUS_CANCELLED)->count(),
            'missing' => max($expectedCount - $sessionDates->count(), 0),
            'completionRate' => $expectedCount > 0 ? round(($sessions->where('status', PreschoolAttendanceSession::STATUS_COMPLETED)->count() / $expectedCount) * 100, 2) : 0.0,
            'attendanceRate' => $attendanceTotal > 0 ? round(($presentCount / $attendanceTotal) * 100, 2) : 0.0,
        ];
    }

    private function sessionQuery(User $actor, PreschoolScheduleEntry $schedule, array $filters = []): Builder
    {
        $this->ensureCanViewSchedule($actor, $schedule);

        $query = PreschoolAttendanceSession::query()
            ->with([
                'preschoolClass.teacher',
                'schedule',
                'createdBy',
                'openedBy',
                'completedBy',
                'lockedBy',
                'closedBy',
                'reopenedBy',
                'cancelledBy',
                'attendanceRecords.student',
            ])
            ->withCount('attendanceRecords')
            ->where('schedule_id', $schedule->id)
            ->orderByDesc('attendance_date')
            ->orderByDesc('id');

        if (($dateFrom = $this->normalizeDate($filters['date_from'] ?? null)) instanceof Carbon) {
            $query->whereDate('attendance_date', '>=', $dateFrom->toDateString());
        }

        if (($dateTo = $this->normalizeDate($filters['date_to'] ?? null)) instanceof Carbon) {
            $query->whereDate('attendance_date', '<=', $dateTo->toDateString());
        }

        if (($status = $this->normalizeStatus($filters['status'] ?? null)) !== null) {
            $query->where('status', $status);
        }

        return $query;
    }

    private function relatedAlerts(User $actor, PreschoolScheduleEntry $schedule, Carbon $dateFrom, Carbon $dateTo): array
    {
        $alerts = app(PreschoolAttendanceAlertService::class)->listAttendanceAlerts($actor, [
            'class_id' => $schedule->class_id,
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
            'page' => 1,
            'per_page' => 5,
        ]);

        return [
            'summary' => $alerts['summary'] ?? [],
            'items' => $alerts['items'] ?? [],
            'pagination' => $alerts['pagination'] ?? null,
        ];
    }

    private function relatedGuardianContacts(User $actor, PreschoolScheduleEntry $schedule, Carbon $dateFrom, Carbon $dateTo): array
    {
        $communications = app(PreschoolGuardianCommunicationService::class)->listCommunications($actor, [
            'source_type' => 'attendance',
            'class_id' => $schedule->class_id,
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
            'page' => 1,
            'per_page' => 5,
        ]);

        return [
            'items' => PreschoolGuardianCommunicationResource::collection($communications->getCollection())->resolve(request()),
            'pagination' => [
                'currentPage' => $communications->currentPage(),
                'lastPage' => $communications->lastPage(),
                'perPage' => $communications->perPage(),
                'total' => $communications->total(),
            ],
        ];
    }

    private function attendanceRecordsForSchedule(User $actor, PreschoolScheduleEntry $schedule, Carbon $dateFrom, Carbon $dateTo): Collection
    {
        $this->ensureCanViewSchedule($actor, $schedule);

        return PreschoolAttendanceRecord::query()
            ->whereHas('attendanceSession', static function (Builder $query) use ($schedule, $dateFrom, $dateTo): void {
                $query->where('schedule_id', $schedule->id)
                    ->whereDate('attendance_date', '>=', $dateFrom->toDateString())
                    ->whereDate('attendance_date', '<=', $dateTo->toDateString());
            })
            ->get();
    }

    private function expectedSessionDates(PreschoolScheduleEntry $schedule, Carbon $dateFrom, Carbon $dateTo): Collection
    {
        if ($schedule->day_of_week === null) {
            return collect();
        }

        $dates = collect();
        $cursor = $dateFrom->copy()->startOfDay();
        $end = $dateTo->copy()->startOfDay();

        while ($cursor->lte($end)) {
            if ((int) $cursor->dayOfWeekIso === (int) $schedule->day_of_week) {
                $dates->push($cursor->toDateString());
            }

            $cursor->addDay();
        }

        return $dates->values();
    }

    private function defaultHistoryRange(PreschoolScheduleEntry $schedule): array
    {
        $today = now()->startOfDay();
        $start = $this->normalizeDate($schedule->effective_from) ?? $this->earliestSessionDate($schedule) ?? $today->copy();
        $end = $this->normalizeDate($schedule->effective_until) ?? $today->copy();

        if ($end->gt($today)) {
            $end = $today->copy();
        }

        if ($start->gt($end)) {
            $start = $end->copy();
        }

        return [$start->startOfDay(), $end->startOfDay()];
    }

    private function resolveDateRange(PreschoolScheduleEntry $schedule, array $filters = []): array
    {
        $today = now()->startOfDay();
        $start = $this->normalizeDate($filters['date_from'] ?? null)
            ?? $this->normalizeDate($schedule->effective_from)
            ?? $this->earliestSessionDate($schedule)
            ?? $today->copy();
        $end = $this->normalizeDate($filters['date_to'] ?? null)
            ?? $this->normalizeDate($schedule->effective_until)
            ?? $today->copy();

        if ($end->gt($today)) {
            $end = $today->copy();
        }

        if ($start->gt($end)) {
            $start = $end->copy();
        }

        return [$start->startOfDay(), $end->startOfDay()];
    }

    private function earliestSessionDate(PreschoolScheduleEntry $schedule): ?Carbon
    {
        $value = PreschoolAttendanceSession::query()
            ->where('schedule_id', $schedule->id)
            ->orderBy('attendance_date')
            ->orderBy('id')
            ->value('attendance_date');

        return $this->normalizeDate($value);
    }

    private function ensureCanViewSchedule(User $actor, PreschoolScheduleEntry $schedule): void
    {
        if (in_array($actor->role_code, ['superadmin', 'adminpreschool'], true)) {
            return;
        }

        abort_unless(
            $actor->role_code === 'teacher-preschool'
                && in_array($actor->id, [
                    $schedule->teacher_user_id,
                    $schedule->preschoolClass?->teacher_user_id,
                ], true),
            403,
            'Forbidden.',
        );
    }

    private function presentSchedule(PreschoolScheduleEntry $schedule): array
    {
        $schedule->loadMissing(['preschoolClass.teacher', 'teacher', 'academicYear', 'term']);

        return PreschoolScheduleEntryResource::make($schedule)->resolve(request());
    }

    private function normalizeDate(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->startOfDay();
        }

        if (blank($value)) {
            return null;
        }

        return Carbon::parse((string) $value)->startOfDay();
    }

    private function normalizeStatus(mixed $value): ?string
    {
        $status = trim((string) ($value ?? ''));

        if ($status === '') {
            return null;
        }

        return in_array($status, array_merge(PreschoolAttendanceSession::STATUSES, ['closed']), true) ? $status : null;
    }
}
