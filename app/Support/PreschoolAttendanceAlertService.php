<?php

namespace App\Support;

use App\Models\PreschoolAttendanceRecord;
use App\Models\PreschoolClass;
use App\Models\PreschoolGuardianCommunication;
use App\Models\PreschoolStudent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class PreschoolAttendanceAlertService
{
    /**
     * Build a canonical attendance-alert view from preserved guardian
     * communications. The service only reads the persisted alert records.
     */
    public function listAttendanceAlerts(?User $viewer, array $filters = []): array
    {
        $query = PreschoolGuardianCommunication::query()
            ->with([
                'student.classes',
                'guardian',
            ])
            ->where('source_type', 'attendance')
            ->where('communication_type', $this->normalizeCommunicationType($filters['communication_type'] ?? null))
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $this->applyViewerScope($query, $viewer);
        $this->applyFilters($query, $filters);

        $communications = $query->get();
        $normalized = $this->normalizeCommunications($communications, $filters);

        $threshold = $this->normalizeThreshold($filters['threshold'] ?? null);
        if ($threshold !== null) {
            $normalized = $normalized->filter(static function (array $alert) use ($threshold): bool {
                if (($alert['alertType'] ?? '') !== 'repeated_absence') {
                    return true;
                }

                $absenceCount = $alert['absenceCount'];

                return $absenceCount === null || $absenceCount >= $threshold;
            })->values();
        }

        $summary = $this->buildSummary($normalized);
        $page = max((int) ($filters['page'] ?? 1), 1);
        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        $paginator = new LengthAwarePaginator(
            $normalized->forPage($page, $perPage)->values(),
            $normalized->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ],
        );

        return [
            'summary' => $summary,
            'items' => $paginator->getCollection()->values()->all(),
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (($studentId = trim((string) ($filters['student_id'] ?? ''))) !== '') {
            $query->where('student_id', $studentId);
        }

        if (($status = trim((string) ($filters['status'] ?? ''))) !== '') {
            $query->where('status', $status);
        }

        if (($dateFrom = trim((string) ($filters['date_from'] ?? ''))) !== '') {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if (($dateTo = trim((string) ($filters['date_to'] ?? ''))) !== '') {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if (($classId = trim((string) ($filters['class_id'] ?? ''))) !== '') {
            $query->whereHas('student.classes', static function (Builder $classQuery) use ($classId): void {
                $classQuery->where('preschool_classes.id', $classId);
            });
        }
    }

    private function applyViewerScope(Builder $query, ?User $viewer): void
    {
        if (! $viewer || in_array($viewer->role_code, ['superadmin', 'adminpreschool'], true)) {
            return;
        }

        if ($viewer->role_code !== 'teacher-preschool') {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereHas('student.classes', static function (Builder $classQuery) use ($viewer): void {
            $classQuery->where('teacher_user_id', $viewer->id);
        });
    }

    private function normalizeCommunications(Collection $communications, array $filters): Collection
    {
        $attendanceHistoryByStudent = $this->attendanceHistoryByStudent($communications);

        return $communications->map(function (PreschoolGuardianCommunication $communication) use ($attendanceHistoryByStudent, $filters): array {
            $student = $communication->student;
            $guardian = $communication->guardian;
            $class = $this->resolveStudentClass($student, $filters['class_id'] ?? null);
            $alertType = $this->normalizeCommunicationType($communication->communication_type);
            $attendanceContext = $this->attendanceContext($communication, $attendanceHistoryByStudent);

            return [
                'id' => $communication->id,
                'studentId' => $communication->student_id,
                'studentName' => trim(($student?->first_name ?? '').' '.($student?->last_name ?? '')) ?: null,
                'classId' => $class?->id,
                'className' => $class?->name,
                'guardianId' => $communication->guardian_id,
                'guardianName' => $guardian?->full_name ?: null,
                'guardianPhone' => $guardian?->phone ?: null,
                'alertType' => $alertType,
                'alertLabel' => $this->alertLabel($communication, $alertType),
                'status' => $communication->status ?: null,
                'severity' => $communication->severity ?: null,
                'absenceCount' => $attendanceContext['absenceCount'],
                'threshold' => $attendanceContext['threshold'],
                'sourceType' => $communication->source_type,
                'sourceId' => $communication->source_id,
                'message' => $communication->message ?: null,
                'createdAt' => $communication->created_at?->toISOString(),
                'updatedAt' => $communication->updated_at?->toISOString(),
                'acknowledgedAt' => $communication->acknowledged_at?->toISOString(),
                'followUpStatus' => $this->followUpStatus($communication, $attendanceContext),
            ];
        });
    }

    private function attendanceHistoryByStudent(Collection $communications): array
    {
        $studentIds = $communications
            ->pluck('student_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($studentIds === []) {
            return [];
        }

        return PreschoolAttendanceRecord::query()
            ->whereIn('student_id', $studentIds)
            ->orderByDesc('attendance_date')
            ->orderByDesc('id')
            ->get()
            ->groupBy('student_id')
            ->all();
    }

    private function attendanceContext(PreschoolGuardianCommunication $communication, array $attendanceHistoryByStudent): array
    {
        if ($communication->communication_type !== 'repeated_absence') {
            return [
                'absenceCount' => null,
                'threshold' => null,
            ];
        }

        $studentId = $communication->student_id;
        $threshold = app(PreschoolAttendanceConfigurationService::class)->getAbsenceAlertDays();
        $history = $attendanceHistoryByStudent[$studentId] ?? collect();
        $cutoff = $communication->created_at?->copy()->endOfDay();

        if (! $studentId || ! $cutoff instanceof \Illuminate\Support\Carbon) {
            return [
                'absenceCount' => null,
                'threshold' => $threshold,
            ];
        }

        $count = 0;
        foreach ($history as $record) {
            if (! $record instanceof PreschoolAttendanceRecord) {
                continue;
            }

            if ($record->attendance_date === null || $record->attendance_date->gt($cutoff)) {
                continue;
            }

            if ($record->status === 'present') {
                break;
            }

            if (in_array($record->status, ['absent', 'late', 'excused'], true)) {
                $count++;
                continue;
            }
        }

        return [
            'absenceCount' => $count > 0 ? $count : null,
            'threshold' => $threshold,
        ];
    }

    private function resolveStudentClass(?PreschoolStudent $student, mixed $preferredClassId = null): ?PreschoolClass
    {
        if (! $student) {
            return null;
        }

        $classes = $student->relationLoaded('classes')
            ? $student->classes
            : $student->classes()->get();

        if ($classes->isEmpty()) {
            return null;
        }

        $preferredClassId = trim((string) $preferredClassId);
        if ($preferredClassId !== '') {
            $preferred = $classes->firstWhere('id', (int) $preferredClassId);
            if ($preferred instanceof PreschoolClass) {
                return $preferred;
            }
        }

        return $classes
            ->sortByDesc(static function (PreschoolClass $class): int {
                $updatedAt = $class->pivot?->updated_at;

                return $updatedAt ? $updatedAt->getTimestamp() : 0;
            })
            ->first();
    }

    private function alertLabel(PreschoolGuardianCommunication $communication, string $alertType): string
    {
        return match ($alertType) {
            'repeated_absence' => 'Repeated Absence',
            'late_pattern' => 'Late Pattern',
            'attendance_exception' => 'Attendance Exception',
            default => $communication->subject ?: $alertType,
        };
    }

    private function followUpStatus(PreschoolGuardianCommunication $communication, array $attendanceContext): string
    {
        if ($communication->status === 'acknowledged') {
            return 'acknowledged';
        }

        if ($communication->status === 'cancelled') {
            return 'cancelled';
        }

        if ($this->isOverdue($communication)) {
            return 'overdue';
        }

        if (($attendanceContext['absenceCount'] ?? null) !== null) {
            return 'open';
        }

        return $communication->status ?: 'open';
    }

    private function isOverdue(PreschoolGuardianCommunication $communication): bool
    {
        if ($communication->status === 'acknowledged' || $communication->status === 'cancelled') {
            return false;
        }

        $createdAt = $communication->created_at;
        if (! $createdAt) {
            return false;
        }

        return $createdAt->lt(now()->subDay());
    }

    private function buildSummary(Collection $alerts): array
    {
        $open = $alerts->filter(static function (array $alert): bool {
            return in_array($alert['status'] ?? '', ['queued', 'sent'], true);
        })->count();

        $acknowledged = $alerts->filter(static function (array $alert): bool {
            return ($alert['status'] ?? '') === 'acknowledged';
        })->count();

        $overdue = $alerts->filter(static function (array $alert): bool {
            return ($alert['followUpStatus'] ?? '') === 'overdue';
        })->count();

        return [
            'total' => $alerts->count(),
            'open' => $open,
            'acknowledged' => $acknowledged,
            'overdue' => $overdue,
            'byClass' => $this->groupByClass($alerts),
            'bySeverity' => $this->groupBySeverity($alerts),
        ];
    }

    private function groupByClass(Collection $alerts): array
    {
        return $alerts
            ->groupBy(static function (array $alert): string {
                return trim((string) ($alert['classId'] ?? '')) ?: 'unknown';
            })
            ->map(static function (Collection $group, string $key): array {
                $sample = $group->first() ?? [];

                return [
                    'classId' => $key === 'unknown' ? null : $sample['classId'] ?? null,
                    'className' => $sample['className'] ?? null,
                    'total' => $group->count(),
                    'open' => $group->filter(static fn (array $alert): bool => in_array($alert['status'] ?? '', ['queued', 'sent'], true))->count(),
                    'acknowledged' => $group->filter(static fn (array $alert): bool => ($alert['status'] ?? '') === 'acknowledged')->count(),
                    'overdue' => $group->filter(static fn (array $alert): bool => ($alert['followUpStatus'] ?? '') === 'overdue')->count(),
                ];
            })
            ->values()
            ->all();
    }

    private function groupBySeverity(Collection $alerts): array
    {
        return $alerts
            ->groupBy(static fn (array $alert): string => trim((string) ($alert['severity'] ?? 'unknown')) ?: 'unknown')
            ->map(static fn (Collection $group, string $severity): array => [
                'severity' => $severity,
                'total' => $group->count(),
            ])
            ->values()
            ->all();
    }

    private function normalizeThreshold(mixed $value): ?int
    {
        $threshold = (int) trim((string) ($value ?? ''));

        return $threshold > 0 ? $threshold : null;
    }

    private function normalizeCommunicationType(mixed $value): string
    {
        $type = trim((string) ($value ?? ''));

        return in_array($type, ['repeated_absence', 'late_pattern', 'attendance_exception'], true)
            ? $type
            : 'repeated_absence';
    }

}
