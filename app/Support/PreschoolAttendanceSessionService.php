<?php

namespace App\Support;

use App\Models\PreschoolAttendanceRecord;
use App\Models\PreschoolAttendanceSession;
use App\Models\PreschoolClassStudent;
use App\Models\PreschoolScheduleEntry;
use App\Models\User;
use App\Services\AuditLogService;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PreschoolAttendanceSessionService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function paginateSessions(User $actor, array $filters = []): LengthAwarePaginator
    {
        return $this->visibleSessionsQuery($actor, $filters)
            ->orderByDesc('attendance_date')->orderByDesc('id')
            ->paginate(min(max((int) ($filters['per_page'] ?? 10), 1), 100), ['*'], 'page', max((int) ($filters['page'] ?? 1), 1));
    }

    public function todaySessions(User $actor, ?Carbon $date = null): Collection
    {
        $date = ($date ?? BusinessTimezone::nowInBusinessTimezone())->toDateString();
        return $this->visibleSessionsQuery($actor, ['date' => $date])->orderBy('start_time')->orderBy('id')->get();
    }

    public function generateSessionsForDateRange(User $actor, string|Carbon $startDate, string|Carbon|null $endDate = null): Collection
    {
        $this->ensureAdmin($actor);
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate ?? $startDate)->startOfDay();
        if ($end->lt($start)) [$start, $end] = [$end, $start];

        $sessions = collect();
        foreach (CarbonPeriod::create($start, $end) as $date) {
            foreach ($this->schedulesForDate($date)->get() as $schedule) {
                $sessions->push($this->createOrUpdateSessionFromSchedule($actor, $schedule, $date));
            }
        }
        return $sessions->values();
    }

    public function missingSessions(User $actor, string|Carbon|null $startDate = null, string|Carbon|null $endDate = null): Collection
    {
        $query = $this->visibleSessionsQuery($actor, ['date' => null, 'start_date' => $startDate, 'end_date' => $endDate]);
        $now = BusinessTimezone::nowInBusinessTimezone();
        return $query->whereIn('status', [PreschoolAttendanceSession::STATUS_SCHEDULED, PreschoolAttendanceSession::STATUS_OPEN])
            ->where(function (Builder $q) use ($now): void {
                $q->whereDate('attendance_date', '<', $now->toDateString())
                    ->orWhere(function (Builder $sameDay) use ($now): void {
                        $sameDay->whereDate('attendance_date', $now->toDateString())
                            ->where(function (Builder $time) use ($now): void {
                                $time->whereNotNull('closes_at')->where('closes_at', '<=', $now->copy()->setTimezone('UTC'))
                                    ->orWhere(function (Builder $legacy) use ($now): void {
                                        $legacy->whereNull('closes_at')->whereNotNull('end_time')->whereTime('end_time', '<=', $now->format('H:i:s'));
                                    });
                            });
                    });
            })->orderByDesc('attendance_date')->orderByDesc('id')->get();
    }

    public function statusSummary(User $actor, array $filters = []): array
    {
        $sessions = $this->visibleSessionsQuery($actor, $filters)->get();
        return [
            'scheduled' => $sessions->where('status', PreschoolAttendanceSession::STATUS_SCHEDULED)->count(),
            'open' => $sessions->where('status', PreschoolAttendanceSession::STATUS_OPEN)->count(),
            'closed' => $sessions->whereIn('status', [PreschoolAttendanceSession::STATUS_CLOSED, PreschoolAttendanceSession::STATUS_COMPLETED])->count(),
            'completed' => $sessions->whereIn('status', [PreschoolAttendanceSession::STATUS_CLOSED, PreschoolAttendanceSession::STATUS_COMPLETED])->count(),
            'locked' => $sessions->where('status', PreschoolAttendanceSession::STATUS_LOCKED)->count(),
            'cancelled' => $sessions->where('status', PreschoolAttendanceSession::STATUS_CANCELLED)->count(),
            'missing' => $this->missingSessions($actor, $filters['start_date'] ?? $filters['date'] ?? null, $filters['end_date'] ?? $filters['date'] ?? null)->count(),
        ];
    }

    public function createManualSession(User $actor, array $data): PreschoolAttendanceSession
    {
        $this->ensureAdmin($actor);
        $classId = (int) ($data['class_id'] ?? $data['preschool_class_id'] ?? 0);
        $class = \App\Models\PreschoolClass::query()->find($classId);
        if (! $class) $this->fail('SESSION_CLASS_REQUIRED', 'A valid Preschool class is required.');
        $teacherId = $data['teacher_user_id'] ?? $class->teacher_user_id;
        $payload = $this->buildSessionPayload($data, $classId, $teacherId, false, $actor);
        $this->assertNoOverlap($classId, $payload['opens_at'], $payload['closes_at']);
        $session = PreschoolAttendanceSession::query()->create($payload);
        $this->audit('attendance_session.created', $actor, $session, null, $session->toArray());
        return $this->findSessionOrFail($session->id);
    }

    public function openSession(User $actor, PreschoolAttendanceSession $session, ?string $reason = null): PreschoolAttendanceSession
    {
        $this->ensureAdmin($actor);
        $this->transition($actor, $session, PreschoolAttendanceSession::STATUS_OPEN, $reason);
        return $this->findSessionOrFail($session->id);
    }

    public function completeSession(User $actor, PreschoolAttendanceSession $session, ?string $reason = null): PreschoolAttendanceSession
    {
        return $this->closeSession($actor, $session, $reason);
    }

    public function closeSession(User $actor, PreschoolAttendanceSession $session, ?string $reason = null): PreschoolAttendanceSession
    {
        $this->ensureAdmin($actor);
        if ($session->closes_at && BusinessTimezone::nowInBusinessTimezone()->setTimezone('UTC')->greaterThan($session->closes_at) === false && $session->closes_at->isFuture()) {
            if ($reason === null || trim($reason) === '') $this->fail('CLOSE_REASON_REQUIRED', 'A reason is required for early closure.');
        }
        $this->transition($actor, $session, PreschoolAttendanceSession::STATUS_CLOSED, $reason);
        return $this->findSessionOrFail($session->id);
    }

    public function lockSession(User $actor, PreschoolAttendanceSession $session, ?string $reason = null): PreschoolAttendanceSession
    {
        $this->ensureAdmin($actor);
        $this->transition($actor, $session, PreschoolAttendanceSession::STATUS_LOCKED, $reason);
        return $this->findSessionOrFail($session->id);
    }

    public function reopenSession(User $actor, PreschoolAttendanceSession $session, ?string $reason): PreschoolAttendanceSession
    {
        $this->ensureAdmin($actor);
        if (! $reason || trim($reason) === '') $this->fail('REOPEN_REASON_REQUIRED', 'A reopen reason is required.');
        if (! in_array($this->canonicalStatus($session->status), [PreschoolAttendanceSession::STATUS_CLOSED, PreschoolAttendanceSession::STATUS_LOCKED], true)) {
            $this->fail('INVALID_STATUS_TRANSITION', 'Only closed or locked sessions can be reopened.');
        }
        $this->transition($actor, $session, PreschoolAttendanceSession::STATUS_OPEN, $reason);
        return $this->findSessionOrFail($session->id);
    }

    public function cancelSession(User $actor, PreschoolAttendanceSession $session, ?string $reason = null): PreschoolAttendanceSession
    {
        $this->ensureAdmin($actor);
        if (! $reason || trim($reason) === '') $this->fail('CANCELLATION_REASON_REQUIRED', 'A cancellation reason is required.');
        if (! in_array($this->canonicalStatus($session->status), [PreschoolAttendanceSession::STATUS_DRAFT, PreschoolAttendanceSession::STATUS_SCHEDULED, PreschoolAttendanceSession::STATUS_OPEN], true)) {
            $this->fail('INVALID_STATUS_TRANSITION', 'This session cannot be cancelled.');
        }
        $this->transition($actor, $session, PreschoolAttendanceSession::STATUS_CANCELLED, $reason);
        return $this->findSessionOrFail($session->id);
    }

    public function reassignTeacher(User $actor, PreschoolAttendanceSession $session, string $teacherId, string $reason): PreschoolAttendanceSession
    {
        $this->ensureAdmin($actor);
        if (trim($reason) === '') $this->fail('REASSIGNMENT_REASON_REQUIRED', 'A reassignment reason is required.');
        $class = $session->preschoolClass;
        $valid = User::query()->whereKey($teacherId)->where('role_code', 'teacher-preschool')->where('status', 'active')->exists()
            && ((string) $class?->teacher_user_id === (string) $teacherId || \App\Models\PreschoolClassTeacherAssignment::query()->where('class_id', $session->preschool_class_id)->where('teacher_user_id', $teacherId)->where('status', 'active')->exists());
        if (! $valid) $this->fail('TEACHER_NOT_ASSIGNED', 'The replacement Teacher is not assigned to this class.');
        $old = $session->only(['teacher_user_id', 'status']);
        $session->teacher_user_id = $teacherId;
        $session->updated_by_user_id = $actor->id;
        $session->save();
        $this->audit('attendance_session.reassigned', $actor, $session, $old, $session->only(['teacher_user_id', 'status']), $reason);
        return $this->findSessionOrFail($session->id);
    }

    public function saveRecordsForSession(User $actor, PreschoolAttendanceSession $session, array $data): Collection
    {
        $this->ensureSessionViewer($actor, $session);
        $this->assertWritable($actor, $session);
        $records = $this->normalizeRecordPayload($data);
        $ids = array_map(static fn (array $record): int => (int) $record['student_id'], $records);
        if (count($ids) !== count(array_unique($ids))) $this->fail('DUPLICATE_PARTICIPANT_IN_PAYLOAD', 'A participant may appear only once in a submission.');
        $context = app(PreschoolAcademicLifecycleService::class)->currentContext();

        return DB::transaction(function () use ($actor, $session, $records, $context): Collection {
            $saved = collect();
            foreach ($records as $recordData) {
                $this->assertStudentEligible($session, (int) $recordData['student_id']);
                $attendance = PreschoolAttendanceRecord::query()->updateOrCreate(
                    ['attendance_session_id' => $session->id, 'student_id' => (int) $recordData['student_id']],
                    [
                        'class_id' => $session->preschool_class_id,
                        'recorded_by_user_id' => $actor->id,
                        'attendance_date' => $session->attendance_date?->toDateString(),
                        'status' => $recordData['status'],
                        'note' => $recordData['note'] ?? null,
                        'academic_year_id' => $context['academic_year_id'] ?? null,
                        'term_id' => $context['term_id'] ?? null,
                    ],
                );
                $saved->push($attendance->load(['student', 'preschoolClass', 'recordedBy', 'academicYear', 'term', 'attendanceSession.schedule']));
                $this->audit('attendance_record.'.($attendance->wasRecentlyCreated ? 'created' : 'updated'), $actor, $session, null, ['student_id' => $attendance->student_id, 'status' => $attendance->status]);
            }
            $this->audit('attendance_record.bulk_saved', $actor, $session, null, ['count' => $saved->count()]);
            return $saved;
        });
    }

    public function saveLegacyAttendance(User $actor, array $data): PreschoolAttendanceRecord
    {
        if ($actor->role_code === 'teacher-preschool') $this->fail('LEGACY_WRITE_DISABLED', 'New Teacher attendance must be submitted through an Attendance Session.', \Symfony\Component\HttpFoundation\Response::HTTP_GONE);
        if (! empty($data['attendance_session_id'])) {
            $session = $this->findSessionOrFail((int) $data['attendance_session_id']);
            return $this->saveRecordsForSession($actor, $session, ['records' => [$data]])->first();
        }
        if (! in_array($actor->role_code, ['superadmin', 'adminpreschool'], true)) $this->fail('SESSION_REQUIRED', 'An Attendance Session is required for operational attendance.');
        $classId = (int) ($data['class_id'] ?? 0);
        $studentId = (int) ($data['student_id'] ?? 0);
        if (! $classId || ! $studentId) $this->fail('SESSION_REQUIRED', 'An Attendance Session is required for operational attendance.');
        $context = app(PreschoolAcademicLifecycleService::class)->currentContext();
        return PreschoolAttendanceRecord::query()->create([
            'class_id' => $classId,
            'student_id' => $studentId,
            'attendance_session_id' => null,
            'recorded_by_user_id' => $actor->id,
            'attendance_date' => $this->normalizeDate($data['attendance_date'] ?? null) ?? BusinessTimezone::businessDate(),
            'status' => $data['status'],
            'note' => $data['note'] ?? null,
            'academic_year_id' => $context['academic_year_id'] ?? null,
            'term_id' => $context['term_id'] ?? null,
        ]);
    }

    public function findSessionOrFail(int|string $id): PreschoolAttendanceSession
    {
        return PreschoolAttendanceSession::query()->with([
            'preschoolClass.teacher', 'schedule', 'sourceSchedule', 'teacher', 'createdBy', 'openedBy', 'completedBy',
            'lockedBy', 'reopenedBy', 'cancelledBy', 'closedBy', 'attendanceRecords.student',
        ])->findOrFail($id);
    }

    public function createOrUpdateSessionFromSchedule(User $actor, PreschoolScheduleEntry $schedule, Carbon $date): PreschoolAttendanceSession
    {
        $teacherId = $schedule->teacher_user_id ?: $schedule->preschoolClass?->teacher_user_id;
        $payload = $this->buildSessionPayload([
            'schedule_id' => $schedule->id,
            'preschool_schedule_entry_id' => $schedule->id,
            'attendance_date' => $date->toDateString(),
            'start_time' => $schedule->start_time,
            'end_time' => $schedule->end_time,
            'status' => PreschoolAttendanceSession::STATUS_SCHEDULED,
            'notes' => $schedule->notes,
        ], (int) $schedule->class_id, $teacherId, true, $actor);
        $payload['session_key'] = $this->sessionKey((int) $schedule->class_id, $date, (int) $schedule->id);
        $payload['source_occurrence_key'] = $payload['session_key'];
        $session = PreschoolAttendanceSession::query()->firstOrCreate(['session_key' => $payload['session_key']], $payload);
        if (! $session->wasRecentlyCreated && $this->canonicalStatus($session->status) === PreschoolAttendanceSession::STATUS_SCHEDULED && $session->attendanceRecords()->doesntExist()) {
            $session->fill(array_intersect_key($payload, array_flip(['teacher_user_id', 'opens_at', 'closes_at', 'start_time', 'end_time', 'notes'])))->save();
        }
        return $this->findSessionOrFail($session->id);
    }

    private function visibleSessionsQuery(User $actor, array $filters = []): Builder
    {
        $hasDate = array_key_exists('date', $filters);
        $date = $this->normalizeDate($filters['date'] ?? null);
        $query = PreschoolAttendanceSession::query()->with(['preschoolClass.teacher', 'schedule', 'teacher', 'attendanceRecords.student']);
        if (! in_array($actor->role_code, ['superadmin', 'adminpreschool'], true)) {
            $query->where(function (Builder $scope) use ($actor): void {
                $scope->where('teacher_user_id', $actor->id)
                    ->orWhere(function (Builder $legacy) use ($actor): void {
                        $legacy->whereNull('teacher_user_id')->whereHas('preschoolClass', fn (Builder $class) => $class->where('teacher_user_id', $actor->id));
                    });
            });
        }
        if (($start = $this->normalizeDate($filters['start_date'] ?? null)) !== null) $query->whereDate('attendance_date', '>=', $start);
        if (($end = $this->normalizeDate($filters['end_date'] ?? null)) !== null) $query->whereDate('attendance_date', '<=', $end);
        elseif ($date !== null) $query->whereDate('attendance_date', $date);
        elseif (! $hasDate) $query->whereDate('attendance_date', BusinessTimezone::businessDate());
        if (($classId = $this->nullableInt($filters['class_id'] ?? null)) !== null) $query->where('preschool_class_id', $classId);
        if (($status = $this->nullableString($filters['status'] ?? null)) !== null) $query->where('status', $this->normalizeStatus($status));
        return $query;
    }

    private function schedulesForDate(Carbon $date): Builder
    {
        return PreschoolScheduleEntry::query()->with(['preschoolClass.teacher', 'teacher'])->where('status', 'active')->where('day_of_week', $date->isoWeekday())
            ->where(fn (Builder $q) => $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', $date->toDateString()))
            ->where(fn (Builder $q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', $date->toDateString()));
    }

    private function buildSessionPayload(array $data, int $classId, ?string $teacherId, bool $generated, User $actor): array
    {
        $date = $this->normalizeDate($data['attendance_date'] ?? null) ?? BusinessTimezone::businessDate();
        $start = $this->normalizeTime($data['start_time'] ?? null) ?? '08:00:00';
        $end = $this->normalizeTime($data['end_time'] ?? null) ?? '11:00:00';
        if (! $teacherId) $this->fail('TEACHER_NOT_ASSIGNED', 'A responsible Teacher is required.');
        if ($teacherId && ! User::query()->whereKey($teacherId)->where('role_code', 'teacher-preschool')->where('status', 'active')->exists()) $this->fail('TEACHER_NOT_ASSIGNED', 'The responsible Teacher is not active.');
        $assignedToClass = (string) (\App\Models\PreschoolClass::query()->find($classId)?->teacher_user_id) === (string) $teacherId
            || \App\Models\PreschoolClassTeacherAssignment::query()->where('class_id', $classId)->where('teacher_user_id', $teacherId)->where('status', 'active')->exists();
        if (! $assignedToClass) $this->fail('TEACHER_NOT_ASSIGNED', 'The responsible Teacher is not assigned to this class.');
        $opens = BusinessTimezone::parseLocalDateTimeToUtc($date, $start);
        $closes = BusinessTimezone::parseLocalDateTimeToUtc($date, $end);
        if ($closes->lessThanOrEqualTo($opens)) $this->fail('INVALID_ATTENDANCE_WINDOW', 'The attendance closing time must be after opening time.');
        $key = $this->sessionKey($classId, Carbon::parse($date), $data['schedule_id'] ?? null);
        return [
            'session_code' => $data['session_code'] ?? 'PS-ATT-'.strtoupper(substr(hash('sha256', $key), 0, 16)),
            'preschool_class_id' => $classId,
            'preschool_schedule_entry_id' => $data['preschool_schedule_entry_id'] ?? ($data['schedule_id'] ?? null),
            'schedule_id' => $data['schedule_id'] ?? null,
            'teacher_user_id' => $teacherId,
            'attendance_date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'opens_at' => $opens,
            'closes_at' => $closes,
            'status' => $this->normalizeStatus($data['status'] ?? PreschoolAttendanceSession::STATUS_SCHEDULED),
            'title' => $data['title'] ?? null,
            'generated_from_schedule' => $generated,
            'notes' => $data['notes'] ?? null,
            'session_key' => $data['session_key'] ?? $key,
            'source_occurrence_key' => $data['source_occurrence_key'] ?? ($generated ? $key : null),
            'created_by' => $actor->id,
            'created_by_user_id' => $actor->id,
        ];
    }

    private function assertWritable(User $actor, PreschoolAttendanceSession $session): void
    {
        $status = $this->canonicalStatus($session->status);
        if ($status !== PreschoolAttendanceSession::STATUS_OPEN) $this->fail($status === PreschoolAttendanceSession::STATUS_LOCKED ? 'SESSION_LOCKED' : ($status === PreschoolAttendanceSession::STATUS_CANCELLED ? 'SESSION_CANCELLED' : 'SESSION_NOT_OPEN'), 'The Attendance Session is not open.');
        if (! $session->opens_at || ! $session->closes_at) {
            if ($session->attendance_date && $session->start_time && $session->end_time) {
                $session->opens_at = BusinessTimezone::parseLocalDateTimeToUtc($session->attendance_date->toDateString(), $session->start_time);
                $session->closes_at = BusinessTimezone::parseLocalDateTimeToUtc($session->attendance_date->toDateString(), $session->end_time);
            } else {
                $this->fail('INVALID_ATTENDANCE_WINDOW', 'The Attendance Session has no valid attendance window.');
            }
        }
        $now = BusinessTimezone::nowInBusinessTimezone()->setTimezone('UTC');
        if ($now->lessThan($session->opens_at)) $this->fail('SESSION_NOT_STARTED', 'The attendance window has not started.', 409);
        if ($now->greaterThanOrEqualTo($session->closes_at)) $this->fail('SESSION_WINDOW_CLOSED', 'The attendance window is closed.', 409);
        if ($actor->status !== 'active') $this->fail('TEACHER_NOT_ASSIGNED', 'The responsible Teacher is inactive.', 403);
    }

    private function ensureSessionViewer(User $actor, PreschoolAttendanceSession $session): void
    {
        if (in_array($actor->role_code, ['superadmin', 'adminpreschool'], true)) return;
        $assigned = (string) $session->teacher_user_id === (string) $actor->id
            || ($session->teacher_user_id === null && (string) $session->preschoolClass?->teacher_user_id === (string) $actor->id);
        if ($actor->role_code !== 'teacher-preschool' || ! $assigned) $this->fail('SESSION_NOT_ASSIGNED', 'This Attendance Session is not assigned to you.', 403);
    }

    private function assertStudentEligible(PreschoolAttendanceSession $session, int $studentId): void
    {
        $date = $session->attendance_date?->toDateString();
        $eligible = PreschoolClassStudent::query()->where('class_id', $session->preschool_class_id)->where('student_id', $studentId)
            ->where('status', 'active')->where('enrollment_status', 'active')
            ->where(fn (Builder $q) => $q->whereNull('enrollment_started_at')->orWhereDate('enrollment_started_at', '<=', $date))
            ->where(fn (Builder $q) => $q->whereNull('enrollment_ended_at')->orWhereDate('enrollment_ended_at', '>=', $date))->exists();
        if (! $eligible) $this->fail('PARTICIPANT_NOT_ELIGIBLE', 'The student is not eligible for this Attendance Session.', 422);
    }

    private function ensureAdmin(User $actor): void
    {
        if (! in_array($actor->role_code, ['superadmin', 'adminpreschool'], true)) $this->fail('FORBIDDEN', 'Forbidden.', 403);
    }

    private function transition(User $actor, PreschoolAttendanceSession $session, string $next, ?string $reason): void
    {
        $current = $this->canonicalStatus($session->status);
        $allowed = [
            PreschoolAttendanceSession::STATUS_DRAFT => [PreschoolAttendanceSession::STATUS_SCHEDULED, PreschoolAttendanceSession::STATUS_CANCELLED],
            PreschoolAttendanceSession::STATUS_SCHEDULED => [PreschoolAttendanceSession::STATUS_DRAFT, PreschoolAttendanceSession::STATUS_OPEN, PreschoolAttendanceSession::STATUS_CANCELLED],
            PreschoolAttendanceSession::STATUS_OPEN => [PreschoolAttendanceSession::STATUS_CLOSED, PreschoolAttendanceSession::STATUS_LOCKED, PreschoolAttendanceSession::STATUS_CANCELLED],
            PreschoolAttendanceSession::STATUS_CLOSED => [PreschoolAttendanceSession::STATUS_OPEN, PreschoolAttendanceSession::STATUS_LOCKED],
            PreschoolAttendanceSession::STATUS_LOCKED => [PreschoolAttendanceSession::STATUS_OPEN],
        ];
        if (! in_array($next, $allowed[$current] ?? [], true)) $this->fail('INVALID_STATUS_TRANSITION', 'The requested status transition is not allowed.', 409);
        if ($next === PreschoolAttendanceSession::STATUS_OPEN && in_array($current, [PreschoolAttendanceSession::STATUS_CLOSED, PreschoolAttendanceSession::STATUS_LOCKED], true) && (! $reason || trim($reason) === '')) $this->fail('REOPEN_REASON_REQUIRED', 'A reopen reason is required.');
        $old = $session->only(['status', 'teacher_user_id', 'opens_at', 'closes_at']);
        $now = BusinessTimezone::nowInBusinessTimezone()->setTimezone('UTC');
        $session->status = $next;
        if ($next === PreschoolAttendanceSession::STATUS_OPEN) { $session->opened_by = $actor->id; $session->opened_by_user_id = $actor->id; $session->opened_at = $now; }
        if ($next === PreschoolAttendanceSession::STATUS_CLOSED) { $session->closed_by = $actor->id; $session->closed_by_user_id = $actor->id; $session->closed_at = $now; $session->completed_by = $actor->id; $session->completed_at = $now; }
        if ($next === PreschoolAttendanceSession::STATUS_LOCKED) { $session->locked_by = $actor->id; $session->locked_by_user_id = $actor->id; $session->locked_at = $now; }
        if ($next === PreschoolAttendanceSession::STATUS_CANCELLED) { $session->cancelled_by = $actor->id; $session->cancelled_by_user_id = $actor->id; $session->cancelled_at = $now; $session->cancellation_reason = $reason; }
        if ($next === PreschoolAttendanceSession::STATUS_OPEN && in_array($current, [PreschoolAttendanceSession::STATUS_CLOSED, PreschoolAttendanceSession::STATUS_LOCKED], true)) { $session->reopened_by = $actor->id; $session->last_reopened_by_user_id = $actor->id; $session->reopened_at = $now; $session->last_reopened_at = $now; }
        $session->save();
        $this->audit('attendance_session.'.($next === 'closed' ? 'closed' : ($next === 'open' && in_array($current, ['closed', 'locked'], true) ? 'reopened' : $next)), $actor, $session, $old, $session->only(['status', 'teacher_user_id', 'opens_at', 'closes_at']), $reason);
    }

    private function assertNoOverlap(int $classId, Carbon $opensAt, Carbon $closesAt): void
    {
        $exists = PreschoolAttendanceSession::query()->where('preschool_class_id', $classId)->whereNotIn('status', [PreschoolAttendanceSession::STATUS_CANCELLED])
            ->where('opens_at', '<', $closesAt)->where('closes_at', '>', $opensAt)->exists();
        if ($exists) $this->fail('OVERLAPPING_SESSION', 'The class already has an overlapping Attendance Session.', 409);
    }

    private function audit(string $action, User $actor, PreschoolAttendanceSession $session, ?array $old, ?array $new, ?string $reason = null): void
    {
        $this->auditLogService->recordSafely([
            'actor_user_id' => $actor->id, 'domain' => 'preschool_attendance', 'action' => $action,
            'entity_type' => PreschoolAttendanceSession::class, 'entity_id' => $session->id,
            'entity_label' => $session->session_code ?: $session->session_key,
            'old_values' => $old, 'new_values' => $new,
            'metadata' => ['reason' => $reason, 'business_timezone' => BusinessTimezone::TIMEZONE],
        ]);
    }

    private function fail(string $code, string $message, int $status = 422): never
    {
        throw new AttendanceError($code, $message, $status);
    }

    private function normalizeRecordPayload(array $data): array
    {
        $records = $data['records'] ?? [$data];
        return collect($records)->map(static fn (array $r): array => ['student_id' => $r['student_id'] ?? $r['participantId'] ?? 0, 'status' => $r['status'] ?? 'present', 'note' => $r['note'] ?? null])->all();
    }

    private function canonicalStatus(?string $status): string
    {
        return $status === PreschoolAttendanceSession::STATUS_COMPLETED ? PreschoolAttendanceSession::STATUS_CLOSED : (string) $status;
    }

    private function normalizeStatus(mixed $status): string
    {
        return $this->canonicalStatus(trim((string) $status)) ?: PreschoolAttendanceSession::STATUS_SCHEDULED;
    }

    private function normalizeDate(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : Carbon::parse($value, BusinessTimezone::TIMEZONE)->toDateString();
    }

    private function normalizeTime(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : Carbon::parse((string) $value)->format('H:i:s');
    }

    private function nullableInt(mixed $value): ?int { return $value === null || $value === '' ? null : (int) $value; }
    private function nullableString(mixed $value): ?string { $value = trim((string) $value); return $value === '' ? null : $value; }
    private function sessionKey(int $classId, Carbon $date, ?int $scheduleId): string { return 'class:'.$classId.':'.$date->toDateString().':'.($scheduleId ? 'schedule-'.$scheduleId : 'manual-'.bin2hex(random_bytes(4))); }
}
