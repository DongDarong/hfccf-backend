<?php

namespace App\Support;

use App\Http\Resources\Preschool\PreschoolAttendanceSessionResource;
use App\Http\Resources\Preschool\PreschoolGuardianCommunicationResource;
use App\Http\Resources\Preschool\PreschoolScheduleEntryResource;
use App\Models\PreschoolAcademicYear;
use App\Models\PreschoolAttendanceRecord;
use App\Models\PreschoolAttendanceSession;
use App\Models\PreschoolClass;
use App\Models\PreschoolGuardianCommunication;
use App\Models\PreschoolScheduleEntry;
use App\Models\PreschoolStudent;
use App\Models\PreschoolStudentAssessment;
use App\Models\User;
use App\Services\PreschoolGuardianCommunicationService;
use App\Support\PreschoolReporting\PreschoolAnalyticsService as PreschoolAnalyticsMathService;
use App\Support\PreschoolReporting\PreschoolReportingService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PreschoolAnalyticsService
{
    public function __construct(
        private readonly PreschoolReportingService $reportingService,
        private readonly PreschoolAttendanceSessionService $attendanceSessionService,
        private readonly PreschoolAttendanceAlertService $attendanceAlertService,
        private readonly PreschoolScheduleSessionHistoryService $scheduleHistoryService,
        private readonly PreschoolGuardianCommunicationService $guardianCommunicationService,
        private readonly PreschoolAnalyticsMathService $math,
        private readonly PreschoolAcademicLifecycleService $academicLifecycle,
        private readonly PreschoolAssessmentAggregationService $assessmentAggregation,
    ) {}

    public function dashboard(User $user, array $filters = []): array
    {
        $filters = $this->normalizeFilters($filters);
        $reportFilters = $this->reportingFilters($filters);
        $baseReport = $this->reportingService->dashboard($user, $reportFilters);
        $attendance = $this->attendance($user, $filters);
        $sessions = $this->sessions($user, $filters);
        $schedules = $this->schedules($user, $filters);
        $alerts = $this->alerts($user, $filters);
        $students = $this->students($user, $filters);
        $teachers = $this->teachers($user, $filters);
        $guardianContacts = $this->guardianContacts($user, $filters);

        return [
            'summary' => [
                'activeStudents' => $students['summary']['activeStudents'] ?? 0,
                'attendanceRate' => $attendance['summary']['attendanceRate'] ?? 0,
                'sessionsGenerated' => $sessions['summary']['totalSessions'] ?? 0,
                'sessionsCompleted' => $sessions['summary']['completed'] ?? 0,
                'activeSchedules' => $schedules['summary']['activeSchedules'] ?? 0,
                'totalAlerts' => $alerts['summary']['totalAlerts'] ?? 0,
                'guardianContacts' => $guardianContacts['summary']['contactLogs'] ?? 0,
                'assignedClasses' => $teachers['summary']['assignedClasses'] ?? 0,
            ],
            'trends' => [
                'attendance' => $attendance['trends'] ?? [],
                'sessions' => $sessions['trends'] ?? [],
                'alerts' => $alerts['trends'] ?? [],
                'guardianContacts' => $guardianContacts['trends'] ?? [],
            ],
            'breakdowns' => [
                'attendance' => $attendance['breakdowns'] ?? [],
                'sessions' => $sessions['breakdowns'] ?? [],
                'schedules' => $schedules['breakdowns'] ?? [],
                'alerts' => $alerts['breakdowns'] ?? [],
                'students' => $students['breakdowns'] ?? [],
                'teachers' => $teachers['breakdowns'] ?? [],
            ],
            'charts' => [
                'attendance' => $attendance['charts'] ?? [],
                'sessions' => $sessions['charts'] ?? [],
                'schedules' => $schedules['charts'] ?? [],
                'alerts' => $alerts['charts'] ?? [],
                'guardianContacts' => $guardianContacts['charts'] ?? [],
            ],
            'datasets' => [
                'recentAlerts' => $alerts['datasets']['recentAlerts'] ?? [],
                'recentGuardianContacts' => $guardianContacts['datasets']['recentCommunications'] ?? [],
                'topClasses' => $attendance['datasets']['topClasses'] ?? [],
            ],
            'filters' => $reportFilters,
            'reportingDashboard' => $baseReport,
            'generatedAt' => now()->toISOString(),
        ];
    }

    public function attendance(User $user, array $filters = []): array
    {
        $filters = $this->normalizeFilters($filters);
        $report = $this->reportingService->section($user, 'attendance', $this->reportingFilters($filters));
        $records = $this->attendanceRecords($user, $filters)
            ->with(['student', 'preschoolClass.teacher', 'attendanceSession'])
            ->get();

        $summary = $this->attendanceSummary($records);
        $window = $this->windowSummary($user, $filters, 'attendance');

        return [
            'summary' => $summary,
            'trends' => [
                'today' => $window['today'],
                'yesterday' => $window['yesterday'],
                'thisWeek' => $window['thisWeek'],
                'thisMonth' => $window['thisMonth'],
                'academicTerm' => $window['academicTerm'],
                'academicYear' => $window['academicYear'],
                'previousWeek' => $window['previousWeek'],
                'previousMonth' => $window['previousMonth'],
            ],
            'breakdowns' => [
                'byClass' => $this->attendanceBreakdownByClass($records),
                'byTeacher' => $this->attendanceBreakdownByTeacher($records),
                'byStudent' => $this->attendanceBreakdownByStudent($records),
                'byWeek' => $this->attendanceBreakdownByPeriod($records, 'Y-W'),
                'byMonth' => $this->attendanceBreakdownByPeriod($records, 'Y-m'),
            ],
            'charts' => [
                'statusDistribution' => $this->chartSeries($summary['statusCounts'], 'status'),
                'byClass' => $this->chartSeries($this->attendanceBreakdownByClass($records), 'class'),
                'trend' => $this->chartSeries($this->attendanceBreakdownByPeriod($records, 'Y-m'), 'period'),
            ],
            'datasets' => [
                'topClasses' => $this->attendanceBreakdownByClass($records),
                'topStudents' => $this->attendanceBreakdownByStudent($records),
            ],
            'report' => $report,
            'filters' => $this->reportingFilters($filters),
            'generatedAt' => now()->toISOString(),
        ];
    }

    public function sessions(User $user, array $filters = []): array
    {
        $filters = $this->normalizeFilters($filters);
        $sessions = $this->attendanceSessions($user, $filters)
            ->with(['preschoolClass.teacher', 'schedule', 'attendanceRecords'])
            ->get();

        $missingSessions = $this->attendanceSessionService->missingSessions(
            $user,
            $filters['date_from'] ?? null,
            $filters['date_to'] ?? null,
        );

        $summary = $this->sessionSummary($sessions, $missingSessions);

        return [
            'summary' => $summary,
            'trends' => [
                'byDay' => $this->sessionBreakdownByPeriod($sessions, 'Y-m-d'),
                'byWeek' => $this->sessionBreakdownByPeriod($sessions, 'o-\WW'),
                'byMonth' => $this->sessionBreakdownByPeriod($sessions, 'Y-m'),
            ],
            'breakdowns' => [
                'byTeacher' => $this->sessionBreakdownByTeacher($sessions),
                'byClass' => $this->sessionBreakdownByClass($sessions),
                'byDay' => $this->sessionBreakdownByDay($sessions),
                'byWeek' => $this->sessionBreakdownByPeriod($sessions, 'o-\WW'),
            ],
            'charts' => [
                'statusDistribution' => $this->chartSeries($summary['statusCounts'], 'status'),
                'completionTrend' => $this->chartSeries($this->sessionBreakdownByPeriod($sessions, 'Y-m'), 'period'),
            ],
            'datasets' => [
                'recentSessions' => $this->mapSessionRows($sessions->take(20)),
                'missingSessions' => PreschoolAttendanceSessionResource::collection($missingSessions)->resolve(request()),
            ],
            'filters' => $this->reportingFilters($filters),
            'generatedAt' => now()->toISOString(),
        ];
    }

    public function schedules(User $user, array $filters = []): array
    {
        $filters = $this->normalizeFilters($filters);
        $schedules = $this->scheduleEntries($user, $filters)
            ->with(['preschoolClass.teacher', 'teacher', 'academicYear', 'term'])
            ->get();

        $sessions = $this->attendanceSessions($user, $filters)->get();
        $activeSchedules = $schedules->count();
        $inactiveSchedules = $schedules->where('status', 'inactive')->count();
        $generatedSessions = $sessions->where('generated_from_schedule', true)->count();

        $summary = [
            'activeSchedules' => $activeSchedules,
            'inactiveSchedules' => $inactiveSchedules,
            'weeklySessions' => $schedules->count(),
            'generatedSessions' => $generatedSessions,
            'roomUtilizationRate' => $activeSchedules > 0 ? round(($generatedSessions / max($activeSchedules, 1)) * 100, 2) : 0,
            'teacherUtilizationRate' => $this->percentage($generatedSessions, max($schedules->count(), 1)),
        ];

        return [
            'summary' => $summary,
            'trends' => [
                'weeklySessions' => $this->scheduleBreakdownByDayOfWeek($schedules),
                'generatedSessions' => $this->sessionBreakdownByPeriod($sessions, 'Y-m'),
            ],
            'breakdowns' => [
                'byTeacher' => $this->scheduleBreakdownByTeacher($schedules),
                'byClass' => $this->scheduleBreakdownByClass($schedules),
                'byRoom' => $this->scheduleBreakdownByRoom($schedules),
                'byDay' => $this->scheduleBreakdownByDayOfWeek($schedules),
            ],
            'charts' => [
                'heatmap' => $this->scheduleHeatmap($schedules),
                'roomUtilization' => $this->chartSeries($this->scheduleBreakdownByRoom($schedules), 'room'),
                'teacherUtilization' => $this->chartSeries($this->scheduleBreakdownByTeacher($schedules), 'teacher'),
            ],
            'datasets' => [
                'schedules' => PreschoolScheduleEntryResource::collection($schedules)->resolve(request()),
                'heatmap' => $this->scheduleHeatmap($schedules),
            ],
            'filters' => $this->reportingFilters($filters),
            'generatedAt' => now()->toISOString(),
        ];
    }

    public function alerts(User $user, array $filters = []): array
    {
        $filters = $this->normalizeFilters($filters);
        $alertSummary = $this->attendanceAlertService->summary($user, [
            'class_id' => $filters['class_id'],
            'date_from' => $filters['date_from'],
            'date_to' => $filters['date_to'],
        ]);
        $communications = $this->attendanceAlertCommunications($user, $filters)
            ->with(['student.classes', 'guardian', 'creator'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $summary = [
            'totalAlerts' => $alertSummary['total'] ?? $communications->count(),
            'open' => $alertSummary['open'] ?? $communications->whereIn('status', ['queued', 'sent'])->count(),
            'acknowledged' => $alertSummary['acknowledged'] ?? $communications->where('status', 'acknowledged')->count(),
            'completed' => $alertSummary['acknowledged'] ?? $communications->where('status', 'acknowledged')->count(),
            'overdue' => $alertSummary['overdue'] ?? $communications->where('status', 'sent')->count(),
        ];

        return [
            'summary' => $summary,
            'trends' => [
                'daily' => $this->communicationTrendByPeriod($communications, 'Y-m-d'),
                'weekly' => $this->communicationTrendByPeriod($communications, 'o-\WW'),
                'monthly' => $this->communicationTrendByPeriod($communications, 'Y-m'),
            ],
            'breakdowns' => [
                'bySeverity' => $this->communicationBreakdown($communications, 'severity'),
                'byClass' => $this->communicationBreakdownByClass($communications),
                'byTeacher' => $this->communicationBreakdownByTeacher($communications),
                'byAlertType' => $this->communicationBreakdown($communications, 'communication_type'),
            ],
            'charts' => [
                'severity' => $this->chartSeries($this->communicationBreakdown($communications, 'severity'), 'severity'),
                'status' => $this->chartSeries($this->communicationStatusBreakdown($communications), 'status'),
            ],
            'datasets' => [
                'recentAlerts' => PreschoolGuardianCommunicationResource::collection($communications->take(20))->resolve(request()),
            ],
            'filters' => $this->reportingFilters($filters),
            'generatedAt' => now()->toISOString(),
        ];
    }

    public function students(User $user, array $filters = []): array
    {
        $filters = $this->normalizeFilters($filters);
        $studentQuery = $this->studentQuery($user, $filters);
        $students = $studentQuery->with(['classes', 'guardianCommunications', 'healthAlerts', 'dsamSubmissions'])->get();
        $attendanceRecords = $this->attendanceRecords($user, $filters)->get();
        $communications = $this->communicationsForStudents($user, $filters)->get();

        $summary = [
            'activeStudents' => $students->where('status', 'active')->count(),
            'attendanceRate' => $this->math->percentage($attendanceRecords->where('status', 'present')->count(), max($attendanceRecords->count(), 1)) ?? 0,
            'alertCount' => $communications->count(),
            'guardianContacts' => $communications->count(),
            'healthAlerts' => $students->sum(fn (PreschoolStudent $student): int => $student->healthAlerts?->count() ?? 0),
            'assessmentParticipation' => PreschoolStudentAssessment::query()
                ->when($this->teacherScopesToUser($user), function (Builder $query) use ($user): void {
                    $query->whereIn('class_id', $this->accessibleClassIds($user));
                })
                ->when(($classId = $filters['class_id']) !== null, fn (Builder $query) => $query->where('class_id', $classId))
                ->when(($dateFrom = $filters['date_from']) !== null, fn (Builder $query) => $query->whereDate('assessment_date', '>=', $dateFrom))
                ->when(($dateTo = $filters['date_to']) !== null, fn (Builder $query) => $query->whereDate('assessment_date', '<=', $dateTo))
                ->count(),
        ];

        return [
            'summary' => $summary,
            'breakdowns' => [
                'byClass' => $this->studentBreakdownByClass($students, $attendanceRecords, $communications),
                'byAcademicYear' => $this->studentBreakdownByAcademicYear($attendanceRecords),
            ],
            'charts' => [
                'attendance' => $this->chartSeries($this->studentBreakdownByClass($students, $attendanceRecords, $communications), 'class'),
            ],
            'datasets' => [
                'topStudents' => $this->studentDataset($students, $attendanceRecords, $communications),
            ],
            'filters' => $this->reportingFilters($filters),
            'generatedAt' => now()->toISOString(),
        ];
    }

    public function teachers(User $user, array $filters = []): array
    {
        $filters = $this->normalizeFilters($filters);
        $teacherIds = $this->teacherUserIds($user, $filters);
        $classes = PreschoolClass::query()
            ->whereNull('deleted_at')
            ->when($teacherIds !== [], fn (Builder $query) => $query->whereIn('teacher_user_id', $teacherIds))
            ->when(($classId = $filters['class_id']) !== null, fn (Builder $query) => $query->where('id', $classId))
            ->with(['teacher'])
            ->withCount('students')
            ->get();
        $attendanceSessions = $this->attendanceSessions($user, $filters)->get();
        $communications = $this->communicationsQuery($user, $filters)->get();

        $summary = [
            'assignedClasses' => $classes->count(),
            'students' => $classes->sum('students_count'),
            'attendanceSessions' => $attendanceSessions->count(),
            'completedSessions' => $attendanceSessions->where('status', PreschoolAttendanceSession::STATUS_COMPLETED)->count(),
            'attendanceRate' => $this->math->percentage(
                $attendanceSessions->where('status', PreschoolAttendanceSession::STATUS_COMPLETED)->count(),
                max($attendanceSessions->count(), 1),
            ) ?? 0,
            'alertCount' => $communications->count(),
        ];

        return [
            'summary' => $summary,
            'breakdowns' => [
                'byTeacher' => $this->teacherBreakdown($classes, $attendanceSessions, $communications),
                'byClass' => $this->teacherClassBreakdown($classes, $attendanceSessions),
            ],
            'charts' => [
                'weeklyUtilization' => $this->teacherUtilizationChart($classes, $attendanceSessions),
                'monthlyUtilization' => $this->teacherMonthlyUtilizationChart($classes, $attendanceSessions),
            ],
            'datasets' => [
                'teachers' => $this->teacherDataset($classes, $attendanceSessions, $communications),
            ],
            'filters' => $this->reportingFilters($filters),
            'generatedAt' => now()->toISOString(),
        ];
    }

    public function guardianContacts(User $user, array $filters = []): array
    {
        $filters = $this->normalizeFilters($filters);
        $communications = $this->communicationsQuery($user, $filters)
            ->with(['student.classes', 'guardian', 'creator'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $summary = [
            'contactLogs' => $communications->count(),
            'followUps' => $communications->whereIn('source_type', ['attendance', 'health_alert', 'assessment', 'enrollment'])->count(),
            'completed' => $communications->where('status', 'acknowledged')->count(),
            'outstanding' => $communications->whereIn('status', ['queued', 'sent', 'failed'])->count(),
        ];

        return [
            'summary' => $summary,
            'trends' => [
                'daily' => $this->communicationTrendByPeriod($communications, 'Y-m-d'),
                'weekly' => $this->communicationTrendByPeriod($communications, 'o-\WW'),
                'monthly' => $this->communicationTrendByPeriod($communications, 'Y-m'),
            ],
            'breakdowns' => [
                'byMethod' => $this->communicationBreakdown($communications, 'channel'),
                'byReason' => $this->communicationBreakdown($communications, 'communication_type'),
                'byStaffMember' => $this->communicationBreakdownByStaff($communications),
                'byClass' => $this->communicationBreakdownByClass($communications),
            ],
            'charts' => [
                'method' => $this->chartSeries($this->communicationBreakdown($communications, 'channel'), 'method'),
                'trend' => $this->chartSeries($this->communicationTrendByPeriod($communications, 'Y-m-d'), 'period'),
            ],
            'datasets' => [
                'recentCommunications' => PreschoolGuardianCommunicationResource::collection($communications->take(20))->resolve(request()),
            ],
            'filters' => $this->reportingFilters($filters),
            'generatedAt' => now()->toISOString(),
        ];
    }

    public function reportAttendance(User $user, array $filters = []): array
    {
        $payload = $this->attendance($user, $filters);

        return [
            'report' => 'attendance',
            'columns' => [
                ['key' => 'label', 'label' => 'Label'],
                ['key' => 'value', 'label' => 'Value'],
            ],
            'rows' => $payload['breakdowns']['byClass'] ?? [],
            'summary' => $payload['summary'] ?? [],
            'charts' => $payload['charts'] ?? [],
            'filters' => $payload['filters'] ?? [],
            'generatedAt' => $payload['generatedAt'] ?? now()->toISOString(),
        ];
    }

    public function reportSessions(User $user, array $filters = []): array
    {
        $payload = $this->sessions($user, $filters);

        return [
            'report' => 'sessions',
            'columns' => [
                ['key' => 'label', 'label' => 'Label'],
                ['key' => 'value', 'label' => 'Value'],
            ],
            'rows' => $payload['breakdowns']['byClass'] ?? [],
            'summary' => $payload['summary'] ?? [],
            'charts' => $payload['charts'] ?? [],
            'filters' => $payload['filters'] ?? [],
            'generatedAt' => $payload['generatedAt'] ?? now()->toISOString(),
        ];
    }

    public function reportSchedules(User $user, array $filters = []): array
    {
        $payload = $this->schedules($user, $filters);

        return [
            'report' => 'schedules',
            'columns' => [
                ['key' => 'label', 'label' => 'Label'],
                ['key' => 'value', 'label' => 'Value'],
            ],
            'rows' => $payload['breakdowns']['byClass'] ?? [],
            'summary' => $payload['summary'] ?? [],
            'charts' => $payload['charts'] ?? [],
            'filters' => $payload['filters'] ?? [],
            'generatedAt' => $payload['generatedAt'] ?? now()->toISOString(),
        ];
    }

    private function normalizeFilters(array $filters): array
    {
        $normalized = [
            'academic_year_id' => $this->nullableInt($filters['academic_year_id'] ?? $filters['academicYearId'] ?? null),
            'class_id' => $this->nullableInt($filters['class_id'] ?? $filters['classId'] ?? null),
            'teacher_user_id' => $this->nullableString($filters['teacher_user_id'] ?? $filters['teacherUserId'] ?? $filters['teacherId'] ?? null),
            'date_from' => $this->nullableDate($filters['date_from'] ?? $filters['dateFrom'] ?? null),
            'date_to' => $this->nullableDate($filters['date_to'] ?? $filters['dateTo'] ?? null),
            'status' => $this->nullableString($filters['status'] ?? null),
        ];

        $context = $this->academicLifecycle->currentContext();
        if ($normalized['academic_year_id'] === null && ! empty($context['academic_year_id'])) {
            $normalized['academic_year_id'] = (int) $context['academic_year_id'];
        }

        if ($normalized['date_to'] === null) {
            $normalized['date_to'] = now()->toDateString();
        }

        if ($normalized['date_from'] === null) {
            $year = $this->currentAcademicYear();
            $normalized['date_from'] = $year?->start_date?->toDateString() ?? now()->startOfMonth()->toDateString();
        }

        return $normalized;
    }

    private function reportingFilters(array $filters): array
    {
        return [
            'academicYearId' => $filters['academic_year_id'],
            'classId' => $filters['class_id'],
            'teacherId' => $filters['teacher_user_id'],
            'dateFrom' => $filters['date_from'],
            'dateTo' => $filters['date_to'],
            'status' => $filters['status'],
        ];
    }

    private function attendanceRecords(User $user, array $filters): Builder
    {
        $query = PreschoolAttendanceRecord::query()
            ->with(['preschoolClass.teacher', 'student', 'attendanceSession'])
            ->whereDate('attendance_date', '>=', $filters['date_from'])
            ->whereDate('attendance_date', '<=', $filters['date_to']);

        $this->applyClassScope($query, $user, $filters, 'class_id');

        if ($filters['academic_year_id'] !== null) {
            $query->where('academic_year_id', $filters['academic_year_id']);
        }

        if ($filters['status'] !== null) {
            $query->where('status', $filters['status']);
        }

        return $query;
    }

    private function attendanceSessions(User $user, array $filters): Builder
    {
        $query = PreschoolAttendanceSession::query()
            ->with(['preschoolClass.teacher', 'schedule'])
            ->whereDate('attendance_date', '>=', $filters['date_from'])
            ->whereDate('attendance_date', '<=', $filters['date_to']);

        $this->applyClassScope($query, $user, $filters, 'preschool_class_id');

        if ($filters['academic_year_id'] !== null) {
            $query->whereHas('schedule', static function (Builder $scheduleQuery) use ($filters): void {
                $scheduleQuery->where('academic_year_id', $filters['academic_year_id']);
            });
        }

        if ($filters['status'] !== null) {
            $query->where('status', $filters['status']);
        }

        if ($filters['teacher_user_id'] !== null) {
            $query->whereHas('schedule', static function (Builder $scheduleQuery) use ($filters): void {
                $scheduleQuery->where('teacher_user_id', $filters['teacher_user_id']);
            });
        }

        return $query;
    }

    private function scheduleEntries(User $user, array $filters): Builder
    {
        $query = PreschoolScheduleEntry::query()
            ->with(['preschoolClass.teacher', 'teacher', 'academicYear', 'term']);

        $this->applyClassScope($query, $user, $filters, 'class_id');

        if ($filters['academic_year_id'] !== null) {
            $query->where('academic_year_id', $filters['academic_year_id']);
        }

        if ($filters['teacher_user_id'] !== null) {
            $query->where('teacher_user_id', $filters['teacher_user_id']);
        }

        if ($filters['status'] !== null) {
            $query->where('status', $filters['status']);
        }

        return $query;
    }

    private function communicationsQuery(User $user, array $filters, ?string $sourceType = null): Builder
    {
        $query = PreschoolGuardianCommunication::query()
            ->with(['student.classes', 'guardian', 'creator'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($sourceType !== null) {
            $query->where('source_type', $sourceType);
        }

        if ($filters['class_id'] !== null) {
            $query->whereHas('student.classes', static function (Builder $classQuery) use ($filters): void {
                $classQuery->where('preschool_classes.id', $filters['class_id']);
            });
        }

        if ($filters['teacher_user_id'] !== null) {
            $query->whereHas('student.classes', static function (Builder $classQuery) use ($filters): void {
                $classQuery->where('teacher_user_id', $filters['teacher_user_id']);
            });
        }

        if ($filters['date_from'] !== null) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if ($filters['date_to'] !== null) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if ($filters['status'] !== null) {
            $query->where('status', $filters['status']);
        }

        if ($user->role_code === 'teacher-preschool') {
            $query->whereHas('student.classes', static function (Builder $classQuery) use ($user): void {
                $classQuery->where('teacher_user_id', $user->id);
            });
        }

        return $query;
    }

    private function attendanceAlertCommunications(User $user, array $filters): Builder
    {
        $query = $this->communicationsQuery($user, $filters, 'attendance');

        if ($filters['status'] === null) {
            $query->whereIn('communication_type', ['repeated_absence', 'late_pattern', 'attendance_exception']);
        }

        return $query;
    }

    private function communicationsForStudents(User $user, array $filters): Builder
    {
        $query = $this->communicationsQuery($user, $filters);

        return $query;
    }

    private function studentQuery(User $user, array $filters): Builder
    {
        $query = PreschoolStudent::query()->with(['classes', 'guardianCommunications', 'healthAlerts', 'dsamSubmissions']);

        if ($filters['class_id'] !== null) {
            $query->whereHas('classes', static function (Builder $classQuery) use ($filters): void {
                $classQuery->where('preschool_classes.id', $filters['class_id']);
            });
        } elseif ($user->role_code === 'teacher-preschool') {
            $query->whereHas('classes', static function (Builder $classQuery) use ($user): void {
                $classQuery->where('teacher_user_id', $user->id);
            });
        } elseif (($classIds = $this->accessibleClassIds($user)) !== []) {
            $query->whereHas('classes', static function (Builder $classQuery) use ($classIds): void {
                $classQuery->whereIn('preschool_classes.id', $classIds);
            });
        }

        if ($filters['status'] !== null) {
            $query->where('status', $filters['status']);
        }

        return $query;
    }

    private function attendanceSummary(Collection $records): array
    {
        $total = $records->count();
        $present = $records->where('status', 'present')->count();
        $absent = $records->where('status', 'absent')->count();
        $late = $records->where('status', 'late')->count();
        $excused = $records->where('status', 'excused')->count();

        return [
            'total' => $total,
            'present' => $present,
            'absent' => $absent,
            'late' => $late,
            'excused' => $excused,
            'unexcused' => $absent,
            'attendanceRate' => $this->math->percentage($present, max($total, 1)) ?? 0,
            'statusCounts' => [
                'present' => $present,
                'absent' => $absent,
                'late' => $late,
                'excused' => $excused,
            ],
        ];
    }

    private function attendanceBreakdownByClass(Collection $records): array
    {
        return $records->groupBy('class_id')->map(function (Collection $group): array {
            $class = $group->first()?->preschoolClass;
            $total = $group->count();
            $present = $group->where('status', 'present')->count();

            return [
                'classId' => $class?->id,
                'class' => $class?->name ?: 'Unknown class',
                'total' => $total,
                'present' => $present,
                'attendanceRate' => $this->math->percentage($present, max($total, 1)) ?? 0,
            ];
        })->values()->all();
    }

    private function attendanceBreakdownByTeacher(Collection $records): array
    {
        return $records->groupBy(fn (PreschoolAttendanceRecord $record): string => (string) ($record->preschoolClass?->teacher_user_id ?? 'unknown'))
            ->map(function (Collection $group, string $teacherId): array {
                $teacher = $group->first()?->preschoolClass?->teacher;
                $total = $group->count();
                $present = $group->where('status', 'present')->count();

                return [
                    'teacherUserId' => $teacherId === 'unknown' ? null : $teacherId,
                    'teacher' => trim(($teacher?->first_name ?? '').' '.($teacher?->last_name ?? '')) ?: ($teacher?->username ?? 'Unknown teacher'),
                    'total' => $total,
                    'attendanceRate' => $this->math->percentage($present, max($total, 1)) ?? 0,
                ];
            })->values()->all();
    }

    private function attendanceBreakdownByStudent(Collection $records): array
    {
        return $records->groupBy('student_id')->map(function (Collection $group): array {
            $student = $group->first()?->student;
            $total = $group->count();
            $present = $group->where('status', 'present')->count();

            return [
                'studentId' => $student?->id,
                'student' => trim(($student?->first_name ?? '').' '.($student?->last_name ?? '')) ?: 'Unknown student',
                'total' => $total,
                'attendanceRate' => $this->math->percentage($present, max($total, 1)) ?? 0,
            ];
        })->sortByDesc('total')->values()->all();
    }

    private function attendanceBreakdownByPeriod(Collection $records, string $format): array
    {
        return $records
            ->groupBy(fn (PreschoolAttendanceRecord $record): string => $record->attendance_date?->format($format) ?: 'unknown')
            ->map(function (Collection $group, string $period): array {
                $total = $group->count();
                $present = $group->where('status', 'present')->count();

                return [
                    'period' => $period,
                    'total' => $total,
                    'attendanceRate' => $this->math->percentage($present, max($total, 1)) ?? 0,
                ];
            })
            ->values()
            ->all();
    }

    private function sessionSummary(Collection $sessions, Collection $missingSessions): array
    {
        $completed = $sessions->where('status', PreschoolAttendanceSession::STATUS_COMPLETED)->count();
        $total = $sessions->count();
        $records = $sessions->sum(fn (PreschoolAttendanceSession $session): int => $session->attendanceRecords?->count() ?? 0);
        $presentRecords = $sessions->flatMap->attendanceRecords->where('status', 'present')->count();
        $averageDuration = $sessions->filter(fn (PreschoolAttendanceSession $session): bool => $session->status === PreschoolAttendanceSession::STATUS_COMPLETED && $session->start_time !== null && $session->end_time !== null)
            ->map(function (PreschoolAttendanceSession $session): ?float {
                try {
                    $start = Carbon::parse((string) $session->start_time);
                    $end = Carbon::parse((string) $session->end_time);

                    return $end->greaterThanOrEqualTo($start) ? $start->diffInMinutes($end) : null;
                } catch (\Throwable) {
                    return null;
                }
            })
            ->filter()
            ->avg();

        return [
            'totalSessions' => $total,
            'sessionsGenerated' => $sessions->where('generated_from_schedule', true)->count(),
            'open' => $sessions->where('status', PreschoolAttendanceSession::STATUS_OPEN)->count(),
            'completed' => $completed,
            'locked' => $sessions->where('status', PreschoolAttendanceSession::STATUS_LOCKED)->count(),
            'cancelled' => $sessions->where('status', PreschoolAttendanceSession::STATUS_CANCELLED)->count(),
            'missing' => $missingSessions->count(),
            'completionRate' => $this->math->percentage($completed, max($total, 1)) ?? 0,
            'attendanceRate' => $this->math->percentage($presentRecords, max($records, 1)) ?? 0,
            'averageSessionDuration' => round((float) ($averageDuration ?? 0), 2),
            'statusCounts' => [
                'scheduled' => $sessions->where('status', PreschoolAttendanceSession::STATUS_SCHEDULED)->count(),
                'open' => $sessions->where('status', PreschoolAttendanceSession::STATUS_OPEN)->count(),
                'completed' => $completed,
                'locked' => $sessions->where('status', PreschoolAttendanceSession::STATUS_LOCKED)->count(),
                'cancelled' => $sessions->where('status', PreschoolAttendanceSession::STATUS_CANCELLED)->count(),
            ],
        ];
    }

    private function sessionBreakdownByTeacher(Collection $sessions): array
    {
        return $sessions->groupBy(fn (PreschoolAttendanceSession $session): string => (string) ($session->preschoolClass?->teacher_user_id ?? 'unknown'))
            ->map(function (Collection $group, string $teacherId): array {
                $teacher = $group->first()?->preschoolClass?->teacher;
                return [
                    'teacherUserId' => $teacherId === 'unknown' ? null : $teacherId,
                    'teacher' => trim(($teacher?->first_name ?? '').' '.($teacher?->last_name ?? '')) ?: ($teacher?->username ?? 'Unknown teacher'),
                    'total' => $group->count(),
                    'completed' => $group->where('status', PreschoolAttendanceSession::STATUS_COMPLETED)->count(),
                    'open' => $group->where('status', PreschoolAttendanceSession::STATUS_OPEN)->count(),
                ];
            })->values()->all();
    }

    private function sessionBreakdownByClass(Collection $sessions): array
    {
        return $sessions->groupBy('preschool_class_id')->map(function (Collection $group): array {
            $class = $group->first()?->preschoolClass;
            return [
                'classId' => $class?->id,
                'class' => $class?->name ?: 'Unknown class',
                'total' => $group->count(),
                'completed' => $group->where('status', PreschoolAttendanceSession::STATUS_COMPLETED)->count(),
                'missing' => $group->whereIn('status', [PreschoolAttendanceSession::STATUS_SCHEDULED, PreschoolAttendanceSession::STATUS_OPEN])->count(),
            ];
        })->values()->all();
    }

    private function sessionBreakdownByDay(Collection $sessions): array
    {
        return $sessions->groupBy(fn (PreschoolAttendanceSession $session): string => $session->attendance_date?->format('l') ?: 'unknown')
            ->map(fn (Collection $group, string $day): array => [
                'day' => $day,
                'total' => $group->count(),
            ])
            ->values()
            ->all();
    }

    private function sessionBreakdownByPeriod(Collection $sessions, string $format): array
    {
        return $sessions->groupBy(fn (PreschoolAttendanceSession $session): string => $session->attendance_date?->format($format) ?: 'unknown')
            ->map(fn (Collection $group, string $period): array => [
                'period' => $period,
                'total' => $group->count(),
            ])
            ->values()
            ->all();
    }

    private function sessionBreakdownByDayOfWeek(Collection $schedules): array
    {
        return $schedules->groupBy('day_of_week')->map(function (Collection $group, string $day): array {
            return [
                'dayOfWeek' => (int) $day,
                'total' => $group->count(),
            ];
        })->values()->all();
    }

    private function scheduleBreakdownByDayOfWeek(Collection $schedules): array
    {
        return $this->sessionBreakdownByDayOfWeek($schedules);
    }

    private function scheduleBreakdownByTeacher(Collection $schedules): array
    {
        return $schedules->groupBy('teacher_user_id')->map(function (Collection $group, string $teacherId): array {
            $teacher = $group->first()?->teacher;
            return [
                'teacherUserId' => $teacherId ?: null,
                'teacher' => trim(($teacher?->first_name ?? '').' '.($teacher?->last_name ?? '')) ?: ($teacher?->username ?? 'Unknown teacher'),
                'total' => $group->count(),
            ];
        })->values()->all();
    }

    private function scheduleBreakdownByClass(Collection $schedules): array
    {
        return $schedules->groupBy('class_id')->map(function (Collection $group): array {
            $class = $group->first()?->preschoolClass;
            return [
                'classId' => $class?->id,
                'class' => $class?->name ?: 'Unknown class',
                'total' => $group->count(),
            ];
        })->values()->all();
    }

    private function scheduleBreakdownByRoom(Collection $schedules): array
    {
        return $schedules->groupBy(fn (PreschoolScheduleEntry $schedule): string => trim((string) ($schedule->room ?: 'Unknown room')))
            ->map(function (Collection $group, string $room): array {
                return [
                    'room' => $room,
                    'total' => $group->count(),
                ];
            })->values()->all();
    }

    private function scheduleHeatmap(Collection $schedules): array
    {
        return $schedules->groupBy(['day_of_week', fn (PreschoolScheduleEntry $schedule): string => substr((string) $schedule->start_time, 0, 2) ?: 'unknown'])
            ->map(function (Collection $dayBuckets, $day): array {
                return [
                    'dayOfWeek' => (int) $day,
                    'slots' => $dayBuckets->map(fn (Collection $group, string $hour): array => [
                        'hour' => $hour,
                        'value' => $group->count(),
                    ])->values()->all(),
                ];
            })->values()->all();
    }

    private function communicationTrendByPeriod(Collection $communications, string $format): array
    {
        return $communications->groupBy(fn (PreschoolGuardianCommunication $communication): string => $communication->created_at?->format($format) ?: 'unknown')
            ->map(fn (Collection $group, string $period): array => [
                'period' => $period,
                'total' => $group->count(),
            ])
            ->values()
            ->all();
    }

    private function communicationBreakdown(Collection $communications, string $field): array
    {
        return $communications->groupBy($field)
            ->map(fn (Collection $group, string $bucket): array => [
                $field => $bucket ?: 'unknown',
                'total' => $group->count(),
            ])
            ->values()
            ->all();
    }

    private function communicationBreakdownByClass(Collection $communications): array
    {
        return $communications->groupBy(fn (PreschoolGuardianCommunication $communication): string => (string) ($communication->student?->classes?->first()?->id ?? 'unknown'))
            ->map(function (Collection $group, string $classId): array {
                $class = $group->first()?->student?->classes?->first();
                return [
                    'classId' => $classId === 'unknown' ? null : (int) $classId,
                    'class' => $class?->name ?: 'Unknown class',
                    'total' => $group->count(),
                ];
            })->values()->all();
    }

    private function communicationBreakdownByTeacher(Collection $communications): array
    {
        return $communications->groupBy(fn (PreschoolGuardianCommunication $communication): string => (string) ($communication->student?->classes?->first()?->teacher_user_id ?? 'unknown'))
            ->map(function (Collection $group, string $teacherId): array {
                $teacher = $group->first()?->student?->classes?->first()?->teacher;
                return [
                    'teacherUserId' => $teacherId === 'unknown' ? null : $teacherId,
                    'teacher' => trim(($teacher?->first_name ?? '').' '.($teacher?->last_name ?? '')) ?: ($teacher?->username ?? 'Unknown teacher'),
                    'total' => $group->count(),
                ];
            })->values()->all();
    }

    private function communicationBreakdownByStaff(Collection $communications): array
    {
        return $communications->groupBy(fn (PreschoolGuardianCommunication $communication): string => (string) ($communication->creator?->id ?? 'unknown'))
            ->map(function (Collection $group, string $staffId): array {
                $staff = $group->first()?->creator;
                return [
                    'staffUserId' => $staffId === 'unknown' ? null : $staffId,
                    'staffMember' => trim(($staff?->first_name ?? '').' '.($staff?->last_name ?? '')) ?: ($staff?->username ?? 'Unknown staff'),
                    'total' => $group->count(),
                ];
            })->values()->all();
    }

    private function communicationStatusBreakdown(Collection $communications): array
    {
        return [
            ['status' => 'queued', 'total' => $communications->where('status', 'queued')->count()],
            ['status' => 'sent', 'total' => $communications->where('status', 'sent')->count()],
            ['status' => 'acknowledged', 'total' => $communications->where('status', 'acknowledged')->count()],
            ['status' => 'failed', 'total' => $communications->where('status', 'failed')->count()],
            ['status' => 'cancelled', 'total' => $communications->where('status', 'cancelled')->count()],
        ];
    }

    private function studentBreakdownByClass(Collection $students, Collection $attendanceRecords, Collection $communications): array
    {
        return $students->groupBy(fn (PreschoolStudent $student): string => (string) ($student->classes->first()?->id ?? 'unknown'))
            ->map(function (Collection $group, string $classId) use ($attendanceRecords, $communications): array {
                $class = $group->first()?->classes->first();
                $studentIds = $group->pluck('id')->all();
                $studentAttendance = $attendanceRecords->whereIn('student_id', $studentIds);
                $studentAlerts = $communications->whereIn('student_id', $studentIds);

                return [
                    'classId' => $classId === 'unknown' ? null : (int) $classId,
                    'class' => $class?->name ?: 'Unknown class',
                    'students' => $group->count(),
                    'attendanceRate' => $this->math->percentage($studentAttendance->where('status', 'present')->count(), max($studentAttendance->count(), 1)) ?? 0,
                    'alertCount' => $studentAlerts->count(),
                ];
            })->values()->all();
    }

    private function studentBreakdownByAcademicYear(Collection $attendanceRecords): array
    {
        return $attendanceRecords->groupBy(fn (PreschoolAttendanceRecord $record): string => (string) ($record->academic_year_id ?? 'unknown'))
            ->map(fn (Collection $group, string $yearId): array => [
                'academicYearId' => $yearId === 'unknown' ? null : (int) $yearId,
                'students' => $group->pluck('student_id')->filter()->unique()->count(),
            ])->values()->all();
    }

    private function studentDataset(Collection $students, Collection $attendanceRecords, Collection $communications): array
    {
        return $students->map(function (PreschoolStudent $student) use ($attendanceRecords, $communications): array {
            $studentAttendance = $attendanceRecords->where('student_id', $student->id);
            $studentAlerts = $communications->where('student_id', $student->id);

            return [
                'studentId' => $student->id,
                'student' => trim($student->first_name.' '.$student->last_name),
                'class' => $student->classes->first()?->name,
                'attendanceRate' => $this->math->percentage($studentAttendance->where('status', 'present')->count(), max($studentAttendance->count(), 1)) ?? 0,
                'alertCount' => $studentAlerts->count(),
                'guardianContacts' => $studentAlerts->count(),
                'healthAlerts' => $student->healthAlerts?->count() ?? 0,
                'assessmentParticipation' => $student->dsamSubmissions?->count() ?? 0,
            ];
        })->values()->all();
    }

    private function teacherBreakdown(Collection $classes, Collection $attendanceSessions, Collection $communications): array
    {
        return $classes->groupBy('teacher_user_id')->map(function (Collection $group, string $teacherId) use ($attendanceSessions, $communications): array {
            $teacher = $group->first()?->teacher;
            $classIds = $group->pluck('id')->all();
            $teacherSessions = $attendanceSessions->whereIn('preschool_class_id', $classIds);
            $teacherCommunications = $communications->filter(fn (PreschoolGuardianCommunication $communication): bool => in_array($communication->student?->classes?->first()?->id, $classIds, true));

            return [
                'teacherUserId' => $teacherId,
                'teacher' => trim(($teacher?->first_name ?? '').' '.($teacher?->last_name ?? '')) ?: ($teacher?->username ?? 'Unknown teacher'),
                'assignedClasses' => $group->count(),
                'students' => $group->sum('students_count'),
                'attendanceSessions' => $teacherSessions->count(),
                'attendanceRate' => $this->math->percentage($teacherSessions->where('status', PreschoolAttendanceSession::STATUS_COMPLETED)->count(), max($teacherSessions->count(), 1)) ?? 0,
                'alertCount' => $teacherCommunications->count(),
            ];
        })->values()->all();
    }

    private function teacherClassBreakdown(Collection $classes, Collection $attendanceSessions): array
    {
        return $classes->map(function (PreschoolClass $class) use ($attendanceSessions): array {
            $classSessions = $attendanceSessions->where('preschool_class_id', $class->id);

            return [
                'classId' => $class->id,
                'class' => $class->name,
                'teacher' => $class->teacher?->name ?: $class->teacher_display_name,
                'attendanceSessions' => $classSessions->count(),
                'completedSessions' => $classSessions->where('status', PreschoolAttendanceSession::STATUS_COMPLETED)->count(),
            ];
        })->values()->all();
    }

    private function teacherUtilizationChart(Collection $classes, Collection $attendanceSessions): array
    {
        return $this->chartSeries($this->teacherBreakdown($classes, $attendanceSessions, collect()), 'teacher');
    }

    private function teacherMonthlyUtilizationChart(Collection $classes, Collection $attendanceSessions): array
    {
        return $this->chartSeries($this->sessionBreakdownByPeriod($attendanceSessions, 'Y-m'), 'period');
    }

    private function teacherDataset(Collection $classes, Collection $attendanceSessions, Collection $communications): array
    {
        return $this->teacherBreakdown($classes, $attendanceSessions, $communications);
    }

    private function chartSeries(array $items, string $labelKey): array
    {
        return [
            'labels' => collect($items)->map(function ($item, $key) use ($labelKey): string {
                if (! is_array($item)) {
                    return (string) $key;
                }

                return (string) ($item[$labelKey] ?? $item['period'] ?? $item['status'] ?? $item['class'] ?? $item['teacher'] ?? $item['room'] ?? $key ?? '');
            })->values()->all(),
            'series' => collect($items)->map(function ($item): float {
                if (! is_array($item)) {
                    return is_numeric($item) ? (float) $item : 0.0;
                }

                return (float) ($item['total'] ?? $item['value'] ?? $item['count'] ?? 0);
            })->values()->all(),
        ];
    }

    private function mapSessionRows(Collection $sessions): array
    {
        return PreschoolAttendanceSessionResource::collection($sessions)->resolve(request());
    }

    private function windowSummary(User $user, array $filters, string $scope): array
    {
        $today = today();
        $yesterday = $today->copy()->subDay();
        $weekStart = $today->copy()->startOfWeek();
        $monthStart = $today->copy()->startOfMonth();
        $academicYear = $this->currentAcademicYear();
        $term = $this->academicLifecycle->currentTerm($academicYear?->id);

        $build = function (Carbon $from, Carbon $to) use ($user, $filters, $scope): array {
            return $scope === 'attendance'
                ? $this->attendanceWindowMetrics($user, $filters, $from, $to)
                : [];
        };

        return [
            'today' => $build($today, $today),
            'yesterday' => $build($yesterday, $yesterday),
            'thisWeek' => $build($weekStart, $today),
            'thisMonth' => $build($monthStart, $today),
            'academicTerm' => $term
                ? $build(Carbon::parse($term->start_date)->startOfDay(), Carbon::parse($term->end_date ?? $today)->startOfDay())
                : [],
            'academicYear' => $academicYear
                ? $build(Carbon::parse($academicYear->start_date)->startOfDay(), Carbon::parse($academicYear->end_date ?? $today)->startOfDay())
                : [],
            'previousWeek' => $build($weekStart->copy()->subWeek(), $weekStart->copy()->subDay()),
            'previousMonth' => $build($monthStart->copy()->subMonthNoOverflow()->startOfMonth(), $monthStart->copy()->subDay()),
        ];
    }

    private function attendanceWindowMetrics(User $user, array $filters, Carbon $from, Carbon $to): array
    {
        $queryFilters = $filters;
        $queryFilters['date_from'] = $from->toDateString();
        $queryFilters['date_to'] = $to->toDateString();
        $records = $this->attendanceRecords($user, $queryFilters)->get();

        return $this->attendanceSummary($records);
    }

    private function accessibleClassIds(User $user): array
    {
        return $this->assessmentAggregation->accessibleClassIds($user);
    }

    private function applyClassScope(Builder $query, User $user, array $filters, string $column): void
    {
        $classId = $filters['class_id'];

        if ($user->role_code === 'teacher-preschool') {
            $classIds = $this->accessibleClassIds($user);
            $query->whereIn($column, $classIds === [] ? [-1] : $classIds);
        }

        if ($classId !== null) {
            $query->where($column, $classId);
        }
    }

    private function teacherScopesToUser(User $user): bool
    {
        return $user->role_code === 'teacher-preschool';
    }

    private function teacherUserIds(User $user, array $filters): array
    {
        if ($filters['teacher_user_id'] !== null) {
            if ($user->role_code === 'teacher-preschool' && $filters['teacher_user_id'] !== $user->id) {
                abort(403, 'Forbidden.');
            }

            return [$filters['teacher_user_id']];
        }

        if ($user->role_code === 'teacher-preschool') {
            return [$user->id];
        }

        return [];
    }

    private function currentAcademicYear(): ?PreschoolAcademicYear
    {
        return $this->academicLifecycle->currentAcademicYear();
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

    private function nullableDate(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : Carbon::parse($value)->toDateString();
    }

    private function percentage(float|int $numerator, float|int $denominator): float
    {
        return $this->math->percentage($numerator, $denominator) ?? 0;
    }
}
