<?php

namespace App\Support;

use App\Models\PreschoolAttendanceRecord;
use App\Models\PreschoolAttendanceSession;
use App\Models\PreschoolClassStudent;
use App\Models\PreschoolScheduleEntry;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class PreschoolAttendanceSessionService
{
    public function paginateSessions(User $actor, array $filters = []): LengthAwarePaginator
    {
        $query = $this->visibleSessionsQuery($actor, $filters);
        $page = max((int) ($filters['page'] ?? 1), 1);
        $perPage = min(max((int) ($filters['per_page'] ?? 10), 1), 100);

        return $query
            ->orderByDesc('attendance_date')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function todaySessions(User $actor, ?Carbon $date = null): Collection
    {
        $date = ($date ?? now())->startOfDay();

        return $this->visibleSessionsQuery($actor, [
            'date' => $date->toDateString(),
        ])
            ->orderBy('start_time')
            ->orderBy('id')
            ->get();
    }

    public function generateSessionsForDateRange(User $actor, string|Carbon $startDate, string|Carbon|null $endDate = null): Collection
    {
        $this->ensureManager($actor);

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate ?? $startDate)->startOfDay();

        if ($end->lt($start)) {
            [$start, $end] = [$end, $start];
        }

        $sessions = collect();

        foreach (CarbonPeriod::create($start, $end) as $date) {
            foreach ($this->generateSessionsForDate($actor, $date) as $session) {
                $sessions->push($session);
            }
        }

        return $sessions->values();
    }

    public function generateSessionsForDate(User $actor, string|Carbon $date): Collection
    {
        $this->ensureManager($actor);

        $date = Carbon::parse($date)->startOfDay();
        $sessions = collect();

        foreach ($this->schedulesForDate($date)->get() as $schedule) {
            $sessions->push($this->createOrUpdateSessionFromSchedule($actor, $schedule, $date));
        }

        return $sessions->values();
    }

    public function missingSessions(User $actor, string|Carbon|null $startDate = null, string|Carbon|null $endDate = null): Collection
    {
        $query = $this->visibleSessionsQuery($actor, [
            'date' => null,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $now = now();

        return $query
            ->whereIn('status', [
                PreschoolAttendanceSession::STATUS_SCHEDULED,
                PreschoolAttendanceSession::STATUS_OPEN,
            ])
            ->where(function (Builder $builder) use ($now): void {
                $builder->whereDate('attendance_date', '<', $now->toDateString())
                    ->orWhere(function (Builder $dateQuery) use ($now): void {
                        $dateQuery->whereDate('attendance_date', $now->toDateString())
                            ->where(function (Builder $timeQuery) use ($now): void {
                                $timeQuery->whereNull('end_time')
                                    ->orWhereTime('end_time', '<=', $now->format('H:i:s'));
                            });
                    });
            })
            ->orderByDesc('attendance_date')
            ->orderByDesc('start_time')
            ->orderByDesc('id')
            ->get();
    }

    public function statusSummary(User $actor, array $filters = []): array
    {
        $sessions = $this->visibleSessionsQuery($actor, $filters)->get();
        $missing = $this->missingSessions(
            $actor,
            $filters['start_date'] ?? $filters['date'] ?? null,
            $filters['end_date'] ?? $filters['date'] ?? null,
        );

        return [
            'scheduled' => $sessions->where('status', PreschoolAttendanceSession::STATUS_SCHEDULED)->count(),
            'open' => $sessions->where('status', PreschoolAttendanceSession::STATUS_OPEN)->count(),
            'completed' => $sessions->where('status', PreschoolAttendanceSession::STATUS_COMPLETED)->count(),
            'locked' => $sessions->where('status', PreschoolAttendanceSession::STATUS_LOCKED)->count(),
            'cancelled' => $sessions->where('status', PreschoolAttendanceSession::STATUS_CANCELLED)->count(),
            'missing' => $missing->count(),
        ];
    }

    public function createManualSession(User $actor, array $data): PreschoolAttendanceSession
    {
        $this->ensureManager($actor);

        $payload = $this->normalizeSessionPayload($data);
        $payload['generated_from_schedule'] = false;
        $payload['created_by'] = $actor->id;
        $payload['session_key'] = $this->sessionKey(
            $payload['preschool_class_id'],
            Carbon::parse($payload['attendance_date']),
            $payload['schedule_id'],
        );

        $session = PreschoolAttendanceSession::query()->firstOrCreate(
            ['session_key' => $payload['session_key']],
            $payload,
        );

        return $session->refresh()->load([
            'preschoolClass.teacher',
            'schedule',
            'createdBy',
            'openedBy',
            'completedBy',
            'lockedBy',
            'reopenedBy',
            'cancelledBy',
            'closedBy',
            'attendanceRecords.student',
        ]);
    }

    public function openSession(User $actor, PreschoolAttendanceSession $session): PreschoolAttendanceSession
    {
        $this->ensureCanManageSession($actor, $session);

        if (! in_array($session->status, [PreschoolAttendanceSession::STATUS_SCHEDULED, PreschoolAttendanceSession::STATUS_OPEN], true)) {
            abort(422, 'Only scheduled sessions can be opened.');
        }

        return $this->transitionSession($actor, $session, PreschoolAttendanceSession::STATUS_OPEN);
    }

    public function completeSession(User $actor, PreschoolAttendanceSession $session): PreschoolAttendanceSession
    {
        $this->ensureCanManageSession($actor, $session);
        return $this->transitionSession($actor, $session, PreschoolAttendanceSession::STATUS_COMPLETED);
    }

    public function closeSession(User $actor, PreschoolAttendanceSession $session): PreschoolAttendanceSession
    {
        return $this->completeSession($actor, $session);
    }

    public function lockSession(User $actor, PreschoolAttendanceSession $session): PreschoolAttendanceSession
    {
        $this->ensureAdminActor($actor);

        if ($session->status !== PreschoolAttendanceSession::STATUS_COMPLETED) {
            abort(422, 'Only completed sessions can be locked.');
        }

        return $this->transitionSession($actor, $session, PreschoolAttendanceSession::STATUS_LOCKED);
    }

    public function reopenSession(User $actor, PreschoolAttendanceSession $session): PreschoolAttendanceSession
    {
        $this->ensureAdminActor($actor);

        if ($session->status !== PreschoolAttendanceSession::STATUS_LOCKED) {
            abort(422, 'Only locked sessions can be reopened.');
        }

        $session->fill([
            'status' => PreschoolAttendanceSession::STATUS_OPEN,
            'reopened_by' => $actor->id,
            'reopened_at' => now(),
        ]);
        $session->save();

        return $session->refresh()->load([
            'preschoolClass.teacher',
            'schedule',
            'createdBy',
            'openedBy',
            'completedBy',
            'lockedBy',
            'reopenedBy',
            'cancelledBy',
            'closedBy',
            'attendanceRecords.student',
        ]);
    }

    public function cancelSession(User $actor, PreschoolAttendanceSession $session, ?string $reason = null): PreschoolAttendanceSession
    {
        $this->ensureCanManageSession($actor, $session);

        if (! in_array($session->status, [
            PreschoolAttendanceSession::STATUS_SCHEDULED,
            PreschoolAttendanceSession::STATUS_OPEN,
            PreschoolAttendanceSession::STATUS_COMPLETED,
        ], true)) {
            abort(422, 'Only scheduled, open, or completed sessions can be cancelled.');
        }

        $session->fill([
            'status' => PreschoolAttendanceSession::STATUS_CANCELLED,
            'cancelled_by' => $actor->id,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);
        $session->save();

        return $session->refresh()->load([
            'preschoolClass.teacher',
            'schedule',
            'createdBy',
            'openedBy',
            'completedBy',
            'lockedBy',
            'reopenedBy',
            'cancelledBy',
            'closedBy',
            'attendanceRecords.student',
        ]);
    }

    public function saveRecordsForSession(User $actor, PreschoolAttendanceSession $session, array $data): Collection
    {
        $this->ensureCanAccessSession($actor, $session);

        if (in_array($session->status, [PreschoolAttendanceSession::STATUS_LOCKED, PreschoolAttendanceSession::STATUS_CANCELLED], true)) {
            abort(422, 'Cannot edit locked or cancelled session.');
        }

        $records = $this->normalizeRecordPayload($data);
        $academicContext = app(PreschoolAcademicLifecycleService::class)->currentContext();
        $finalize = $this->truthy($data['finalize'] ?? $data['complete'] ?? $data['submit'] ?? false);
        $saved = collect();

        foreach ($records as $recordData) {
            if ($actor->role_code === 'teacher-preschool') {
                $this->ensureTeacherCanAccessSessionStudent($actor, $session, (int) $recordData['student_id']);
            }

            $attendance = PreschoolAttendanceRecord::query()->updateOrCreate(
                [
                    'attendance_session_id' => $session->id,
                    'student_id' => (int) $recordData['student_id'],
                ],
                [
                    'class_id' => $session->preschool_class_id,
                    'recorded_by_user_id' => $actor->id,
                    'attendance_date' => $session->attendance_date?->toDateString(),
                    'status' => $recordData['status'],
                    'note' => $recordData['note'] ?? null,
                    'academic_year_id' => $academicContext['academic_year_id'] ?? null,
                    'term_id' => $academicContext['term_id'] ?? null,
                ],
            );

            $saved->push($attendance->load(['student', 'preschoolClass', 'recordedBy', 'academicYear', 'term', 'attendanceSession.schedule']));
        }

        if ($finalize) {
            $this->transitionSession($actor, $session, PreschoolAttendanceSession::STATUS_COMPLETED);
        } elseif ($session->status === PreschoolAttendanceSession::STATUS_SCHEDULED) {
            $this->transitionSession($actor, $session, PreschoolAttendanceSession::STATUS_OPEN);
        }

        return $saved;
    }

    public function saveLegacyAttendance(User $actor, array $data): PreschoolAttendanceRecord
    {
        $this->ensureManager($actor);

        $payload = $this->normalizeLegacyPayload($data);
        $academicContext = app(PreschoolAcademicLifecycleService::class)->currentContext();

        if ($payload['attendance_session_id'] !== null) {
            $session = $this->findSessionOrFail($payload['attendance_session_id']);
            $this->ensureCanManageSession($actor, $session);

            if (in_array($session->status, [PreschoolAttendanceSession::STATUS_LOCKED, PreschoolAttendanceSession::STATUS_CANCELLED], true)) {
                abort(422, 'Cannot edit locked or cancelled session.');
            }

            $payload['class_id'] = $session->preschool_class_id;
            $payload['attendance_date'] = $session->attendance_date?->toDateString() ?? $payload['attendance_date'];
        }

        return PreschoolAttendanceRecord::query()->create([
            'class_id' => $payload['class_id'],
            'student_id' => $payload['student_id'],
            'attendance_session_id' => $payload['attendance_session_id'],
            'recorded_by_user_id' => $actor->id,
            'attendance_date' => $payload['attendance_date'],
            'status' => $payload['status'],
            'note' => $payload['note'],
            'academic_year_id' => $academicContext['academic_year_id'] ?? null,
            'term_id' => $academicContext['term_id'] ?? null,
        ]);
    }

    public function findSessionOrFail(int|string $id): PreschoolAttendanceSession
    {
        return PreschoolAttendanceSession::query()
            ->with([
                'preschoolClass.teacher',
                'schedule',
                'createdBy',
                'openedBy',
                'completedBy',
                'lockedBy',
                'reopenedBy',
                'cancelledBy',
                'closedBy',
                'attendanceRecords.student',
            ])
            ->findOrFail($id);
    }

    public function createOrUpdateSessionFromSchedule(User $actor, PreschoolScheduleEntry $schedule, Carbon $date): PreschoolAttendanceSession
    {
        $payload = [
            'preschool_class_id' => $schedule->class_id,
            'schedule_id' => $schedule->id,
            'attendance_date' => $date->toDateString(),
            'start_time' => $schedule->start_time,
            'end_time' => $schedule->end_time,
            'status' => PreschoolAttendanceSession::STATUS_SCHEDULED,
            'generated_from_schedule' => true,
            'notes' => $schedule->notes,
            'created_by' => $actor->id,
        ];
        $payload['session_key'] = $this->sessionKey($schedule->class_id, $date, $schedule->id);

        $session = PreschoolAttendanceSession::query()->firstOrCreate(
            ['session_key' => $payload['session_key']],
            $payload,
        );

        if (! $session->wasRecentlyCreated) {
            $dirty = false;

            if ($session->schedule_id === null) {
                $session->schedule_id = $schedule->id;
                $dirty = true;
            }

            if ($session->start_time === null && $schedule->start_time !== null) {
                $session->start_time = $schedule->start_time;
                $dirty = true;
            }

            if ($session->end_time === null && $schedule->end_time !== null) {
                $session->end_time = $schedule->end_time;
                $dirty = true;
            }

            if (! $session->generated_from_schedule) {
                $session->generated_from_schedule = true;
                $dirty = true;
            }

            if ($session->notes === null && $schedule->notes !== null) {
                $session->notes = $schedule->notes;
                $dirty = true;
            }

            if ($dirty) {
                $session->save();
            }
        }

        return $session->refresh()->load([
            'preschoolClass.teacher',
            'schedule',
            'createdBy',
            'openedBy',
            'completedBy',
            'lockedBy',
            'reopenedBy',
            'cancelledBy',
            'closedBy',
            'attendanceRecords.student',
        ]);
    }

    private function visibleSessionsQuery(User $actor, array $filters = []): Builder
    {
        $hasDate = array_key_exists('date', $filters);
        $date = $hasDate ? $this->normalizeDate($filters['date'] ?? null) : null;
        $startDate = $this->normalizeDate($filters['start_date'] ?? null);
        $endDate = $this->normalizeDate($filters['end_date'] ?? null);
        $classId = $this->nullableInt($filters['class_id'] ?? null);
        $status = $this->nullableString($filters['status'] ?? null);
        if ($status !== null) {
            $status = $this->normalizeStatus($status);
        }

        $query = PreschoolAttendanceSession::query()
            ->with([
                'preschoolClass.teacher',
                'schedule',
                'createdBy',
                'openedBy',
                'completedBy',
                'lockedBy',
                'reopenedBy',
                'cancelledBy',
                'closedBy',
                'attendanceRecords.student',
            ]);

        if (! in_array($actor->role_code, ['superadmin', 'adminpreschool'], true)) {
            $query->whereHas('preschoolClass', static function (Builder $builder) use ($actor): void {
                $builder->where('teacher_user_id', $actor->id);
            });
        }

        if ($startDate !== null || $endDate !== null) {
            if ($startDate !== null) {
                $query->whereDate('attendance_date', '>=', $startDate);
            }

            if ($endDate !== null) {
                $query->whereDate('attendance_date', '<=', $endDate);
            }
        } elseif ($date !== null) {
            $query->whereDate('attendance_date', $date);
        } elseif (! $hasDate) {
            $query->whereDate('attendance_date', now()->toDateString());
        }

        if ($classId !== null) {
            $query->where('preschool_class_id', $classId);
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query;
    }

    private function schedulesForDate(Carbon $date): Builder
    {
        return PreschoolScheduleEntry::query()
            ->with(['preschoolClass.teacher', 'teacher'])
            ->where('status', 'active')
            ->where('day_of_week', $date->isoWeekday())
            ->where(function (Builder $builder) use ($date): void {
                $builder->whereNull('effective_from')
                    ->orWhereDate('effective_from', '<=', $date->toDateString());
            })
            ->where(function (Builder $builder) use ($date): void {
                $builder->whereNull('effective_until')
                    ->orWhereDate('effective_until', '>=', $date->toDateString());
            });
    }

    private function normalizeSessionPayload(array $data): array
    {
        return [
            'preschool_class_id' => $this->nullableInt($data['class_id'] ?? $data['preschool_class_id'] ?? null) ?? 0,
            'schedule_id' => $this->nullableInt($data['schedule_id'] ?? null),
            'attendance_date' => $this->normalizeDate($data['attendance_date'] ?? null) ?? now()->toDateString(),
            'start_time' => $this->normalizeTime($data['start_time'] ?? null),
            'end_time' => $this->normalizeTime($data['end_time'] ?? null),
            'status' => $this->normalizeStatus($data['status'] ?? 'open'),
            'generated_from_schedule' => (bool) ($data['generated_from_schedule'] ?? false),
            'notes' => $this->nullableString($data['notes'] ?? null),
            'created_by' => $data['created_by'] ?? null,
            'opened_by' => $data['opened_by'] ?? null,
            'opened_at' => $data['opened_at'] ?? null,
            'completed_by' => $data['completed_by'] ?? null,
            'completed_at' => $data['completed_at'] ?? null,
            'locked_by' => $data['locked_by'] ?? null,
            'locked_at' => $data['locked_at'] ?? null,
            'closed_by' => $data['closed_by'] ?? null,
            'closed_at' => $data['closed_at'] ?? null,
            'reopened_by' => $data['reopened_by'] ?? null,
            'reopened_at' => $data['reopened_at'] ?? null,
            'cancelled_by' => $data['cancelled_by'] ?? null,
            'cancelled_at' => $data['cancelled_at'] ?? null,
            'cancellation_reason' => $this->nullableString($data['cancellation_reason'] ?? null),
        ];
    }

    private function normalizeLegacyPayload(array $data): array
    {
        return [
            'class_id' => $this->nullableInt($data['class_id'] ?? null) ?? 0,
            'student_id' => $this->nullableInt($data['student_id'] ?? null) ?? 0,
            'attendance_session_id' => $this->nullableInt($data['attendance_session_id'] ?? null),
            'attendance_date' => $this->normalizeDate($data['attendance_date'] ?? null) ?? now()->toDateString(),
            'status' => trim((string) ($data['status'] ?? 'present')) ?: 'present',
            'note' => $this->nullableString($data['note'] ?? null),
        ];
    }

    private function normalizeRecordPayload(array $data): array
    {
        if (isset($data['records']) && is_array($data['records'])) {
            return collect($data['records'])
                ->filter(static fn ($record) => is_array($record))
                ->values()
                ->map(static function (array $record): array {
                    return [
                        'student_id' => $record['student_id'] ?? 0,
                        'status' => $record['status'] ?? 'present',
                        'note' => $record['note'] ?? null,
                    ];
                })
                ->all();
        }

        return [[
            'student_id' => $data['student_id'] ?? 0,
            'status' => $data['status'] ?? 'present',
            'note' => $data['note'] ?? null,
        ]];
    }

    private function ensureManager(User $actor): void
    {
        abort_unless(in_array($actor->role_code, ['superadmin', 'adminpreschool'], true), 403, 'Forbidden.');
    }

    private function ensureAdminActor(User $actor): void
    {
        abort_unless(in_array($actor->role_code, ['superadmin', 'adminpreschool'], true), 403, 'Forbidden.');
    }

    private function ensureCanManageSession(User $actor, PreschoolAttendanceSession $session): void
    {
        if (in_array($actor->role_code, ['superadmin', 'adminpreschool'], true)) {
            return;
        }

        abort_unless(
            $actor->role_code === 'teacher-preschool' && $session->preschoolClass?->teacher_user_id === $actor->id,
            403,
            'Forbidden.',
        );
    }

    private function ensureCanAccessSession(User $actor, PreschoolAttendanceSession $session): void
    {
        $this->ensureCanManageSession($actor, $session);
    }

    private function ensureTeacherCanAccessSessionStudent(User $actor, PreschoolAttendanceSession $session, int $studentId): void
    {
        if ($actor->role_code !== 'teacher-preschool') {
            return;
        }

        $hasAccess = PreschoolClassStudent::query()
            ->where('class_id', $session->preschool_class_id)
            ->where('student_id', $studentId)
            ->where('status', 'active')
            ->where('enrollment_status', 'active')
            ->exists();

        abort_unless($hasAccess, 403, 'Forbidden.');
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

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->toDateString();
    }

    private function normalizeTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->format('H:i:s');
    }

    private function normalizeStatus(mixed $value): string
    {
        $value = trim((string) $value);

        return match ($value) {
            'scheduled' => PreschoolAttendanceSession::STATUS_SCHEDULED,
            'open' => PreschoolAttendanceSession::STATUS_OPEN,
            'completed', 'closed' => PreschoolAttendanceSession::STATUS_COMPLETED,
            'locked' => PreschoolAttendanceSession::STATUS_LOCKED,
            'cancelled' => PreschoolAttendanceSession::STATUS_CANCELLED,
            default => PreschoolAttendanceSession::STATUS_OPEN,
        };
    }

    private function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    private function transitionSession(User $actor, PreschoolAttendanceSession $session, string $status): PreschoolAttendanceSession
    {
        $this->ensureSessionTransitionAllowed($session, $status);

        $now = now();
        $payload = ['status' => $status];

        if ($status === PreschoolAttendanceSession::STATUS_OPEN) {
            $payload['opened_by'] = $actor->id;
            $payload['opened_at'] = $now;
            $payload['closed_by'] = null;
            $payload['closed_at'] = null;
        } elseif ($status === PreschoolAttendanceSession::STATUS_COMPLETED) {
            $payload['completed_by'] = $actor->id;
            $payload['completed_at'] = $now;
            $payload['closed_by'] = $actor->id;
            $payload['closed_at'] = $now;
        } elseif ($status === PreschoolAttendanceSession::STATUS_LOCKED) {
            $payload['locked_by'] = $actor->id;
            $payload['locked_at'] = $now;
        }

        $session->fill($payload);
        $session->save();

        return $session->refresh()->load([
            'preschoolClass.teacher',
            'schedule',
            'createdBy',
            'openedBy',
            'completedBy',
            'lockedBy',
            'reopenedBy',
            'cancelledBy',
            'closedBy',
            'attendanceRecords.student',
        ]);
    }

    private function ensureSessionTransitionAllowed(PreschoolAttendanceSession $session, string $status): void
    {
        if ($session->status === PreschoolAttendanceSession::STATUS_CANCELLED) {
            abort(422, 'Cancelled sessions cannot be transitioned.');
        }

        if ($session->status === PreschoolAttendanceSession::STATUS_LOCKED && $status !== PreschoolAttendanceSession::STATUS_OPEN) {
            abort(422, 'Locked sessions must be reopened before editing.');
        }

        if ($status === PreschoolAttendanceSession::STATUS_COMPLETED && $session->status === PreschoolAttendanceSession::STATUS_LOCKED) {
            abort(422, 'Locked sessions cannot be completed again.');
        }
    }

    private function sessionKey(int $classId, Carbon $date, ?int $scheduleId): string
    {
        return implode(':', [
            'class',
            $classId,
            $date->toDateString(),
            $scheduleId !== null ? 'schedule-'.$scheduleId : 'manual',
        ]);
    }
}

