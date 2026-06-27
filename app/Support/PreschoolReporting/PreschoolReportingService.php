<?php

namespace App\Support\PreschoolReporting;

use App\Models\PreschoolAcademicYear;
use App\Models\PreschoolAttendanceRecord;
use App\Models\PreschoolClass;
use App\Models\PreschoolEnrollmentApplication;
use App\Models\PreschoolGuardianCommunication;
use App\Models\PreschoolGuardianGovernanceIssue;
use App\Models\PreschoolHealthAlert;
use App\Models\PreschoolInvoice;
use App\Models\PreschoolLifecycleAuditLog;
use App\Models\PreschoolPayment;
use App\Models\PreschoolReceipt;
use App\Models\PreschoolReportPeriod;
use App\Models\PreschoolReportSnapshot;
use App\Models\PreschoolStudent;
use App\Models\PreschoolStudentAssessment;
use App\Models\PreschoolStudentAllergy;
use App\Models\PreschoolStudentMedicationRecord;
use App\Models\PreschoolStudentVaccinationRecord;
use App\Models\User;
use App\Support\PreschoolAcademicLifecycleService;
use App\Support\PreschoolAssessmentAggregationService;
use App\Support\PreschoolClassroomReportService;
use App\Support\PreschoolExportGovernanceService;
use App\Support\PreschoolReportPeriodService;
use App\Support\PreschoolReportSnapshotService;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PreschoolReportingService
{
    public function __construct(
        private readonly PreschoolAnalyticsService $analytics,
        private readonly PreschoolAcademicLifecycleService $academicLifecycle,
        private readonly PreschoolAssessmentAggregationService $assessmentAggregation,
        private readonly PreschoolReportPeriodService $reportPeriodService,
        private readonly PreschoolReportSnapshotService $snapshotService,
        private readonly PreschoolExportGovernanceService $exportGovernanceService,
        private readonly PreschoolClassroomReportService $classroomReportService,
    ) {}

    public function definitions(?User $user = null): array
    {
        $definitions = [
            'enrollment' => [
                'key' => 'enrollment',
                'label' => 'Enrollment',
                'allowedRoles' => ['superadmin', 'adminpreschool', 'teacher-preschool'],
                'visibleFilters' => ['academicYear', 'term', 'dateRange', 'class', 'student', 'status'],
                'rowsKey' => 'admissions',
                'exportColumns' => [['key' => 'label', 'label' => 'Label'], ['key' => 'value', 'label' => 'Value']],
                'exportFormats' => ['csv', 'excel', 'pdf'],
            ],
            'attendance' => [
                'key' => 'attendance',
                'label' => 'Attendance',
                'allowedRoles' => ['superadmin', 'adminpreschool', 'teacher-preschool'],
                'visibleFilters' => ['academicYear', 'term', 'reportPeriod', 'dateRange', 'class', 'student', 'status'],
                'rowsKey' => 'classBreakdown',
                'exportColumns' => [['key' => 'label', 'label' => 'Label'], ['key' => 'value', 'label' => 'Value']],
                'exportFormats' => ['csv', 'excel', 'pdf'],
            ],
            'assessments' => [
                'key' => 'assessments',
                'label' => 'Assessment',
                'allowedRoles' => ['superadmin', 'adminpreschool', 'teacher-preschool'],
                'visibleFilters' => ['academicYear', 'term', 'dateRange', 'student', 'status'],
                'rowsKey' => 'table',
                'exportColumns' => [['key' => 'label', 'label' => 'Label'], ['key' => 'value', 'label' => 'Value']],
                'exportFormats' => ['csv', 'excel', 'pdf'],
            ],
            'health' => [
                'key' => 'health',
                'label' => 'Health',
                'allowedRoles' => ['superadmin', 'adminpreschool', 'teacher-preschool'],
                'visibleFilters' => ['academicYear', 'term', 'dateRange', 'class', 'student', 'status'],
                'rowsKey' => 'incidents',
                'exportColumns' => [['key' => 'label', 'label' => 'Label'], ['key' => 'value', 'label' => 'Value']],
                'exportFormats' => ['csv', 'excel', 'pdf'],
            ],
            'payments' => [
                'key' => 'payments',
                'label' => 'Billing',
                'allowedRoles' => ['superadmin', 'adminpreschool'],
                'visibleFilters' => ['academicYear', 'term', 'dateRange', 'class', 'status'],
                'rowsKey' => 'revenue',
                'exportColumns' => [['key' => 'label', 'label' => 'Label'], ['key' => 'value', 'label' => 'Value']],
                'exportFormats' => ['csv', 'excel', 'pdf'],
            ],
            'guardians' => [
                'key' => 'guardians',
                'label' => 'Guardian Governance',
                'allowedRoles' => ['superadmin', 'adminpreschool'],
                'visibleFilters' => ['academicYear', 'term', 'dateRange', 'class', 'status'],
                'rowsKey' => 'issues',
                'exportColumns' => [['key' => 'label', 'label' => 'Label'], ['key' => 'value', 'label' => 'Value']],
                'exportFormats' => ['csv', 'excel', 'pdf'],
            ],
            'classroom' => [
                'key' => 'classroom',
                'label' => 'Classroom',
                'allowedRoles' => ['superadmin', 'adminpreschool', 'teacher-preschool'],
                'visibleFilters' => ['academicYear', 'term', 'dateRange', 'class', 'student', 'status'],
                'rowsKey' => 'rows',
                'exportColumns' => [['key' => 'label', 'label' => 'Label'], ['key' => 'value', 'label' => 'Value']],
                'exportFormats' => ['csv', 'excel', 'pdf'],
            ],
            'operations' => [
                'key' => 'operations',
                'label' => 'Operations',
                'allowedRoles' => ['superadmin', 'adminpreschool', 'teacher-preschool'],
                'visibleFilters' => ['academicYear', 'term', 'dateRange', 'class', 'status'],
                'rowsKey' => 'modules',
                'exportColumns' => [['key' => 'label', 'label' => 'Label'], ['key' => 'value', 'label' => 'Value']],
                'exportFormats' => ['csv', 'excel', 'pdf'],
            ],
            'compliance' => [
                'key' => 'compliance',
                'label' => 'Compliance',
                'allowedRoles' => ['superadmin', 'adminpreschool'],
                'visibleFilters' => ['academicYear', 'term', 'dateRange', 'status'],
                'rowsKey' => 'timeline',
                'exportColumns' => [['key' => 'label', 'label' => 'Label'], ['key' => 'value', 'label' => 'Value']],
                'exportFormats' => ['csv', 'excel', 'pdf'],
            ],
        ];

        if ($user && $user->role_code === 'teacher-preschool') {
            return collect($definitions)
                ->filter(fn (array $definition): bool => in_array($user->role_code, $definition['allowedRoles'], true))
                ->map(function (array $definition): array {
                    if ($definition['key'] === 'payments' || $definition['key'] === 'guardians' || $definition['key'] === 'compliance') {
                        return null;
                    }

                    return $definition;
                })
                ->filter()
                ->values()
                ->all();
        }

        return $definitions;
    }

    public function dashboard(User $user, array $filters = []): array
    {
        $payload = $this->operationsReport($user, $filters);
        $payload['report'] = 'dashboard';
        $payload['section'] = 'operations';

        return $payload;
    }

    public function section(User $user, string $section, array $filters = []): array
    {
        $section = $this->normalizeSection($section);

        return match ($section) {
            'attendance' => $this->attendanceReport($user, $filters),
            'assessments' => $this->assessmentReport($user, $filters),
            'health' => $this->healthReport($user, $filters),
            'payments' => $this->billingReport($user, $filters),
            'enrollment' => $this->enrollmentReport($user, $filters),
            'guardians' => $this->guardianReport($user, $filters),
            'classroom' => $this->classroomReport($user, $filters),
            'compliance' => $this->complianceReport($user, $filters),
            default => $this->operationsReport($user, $filters),
        };
    }

    public function export(User $user, string $section, string $format = 'csv', array $filters = []): array
    {
        $section = $this->normalizeSection($section);
        $definition = $this->definition($section);

        if (! in_array($user->role_code, ['superadmin', 'adminpreschool'], true)) {
            throw new RuntimeException('Export access is limited to Preschool admins.');
        }

        $report = $this->section($user, $section, $filters);
        $rows = $this->exportRows($report, $definition['rowsKey'] ?? 'rows');
        $columns = $definition['exportColumns'] ?? [['key' => 'label', 'label' => 'Label'], ['key' => 'value', 'label' => 'Value']];
        $baseName = sprintf('preschool-%s-report-%s', $section, now()->format('Ymd-His'));
        $format = strtolower(trim($format));

        return match ($format) {
            'excel', 'xlsx' => [
                'section' => $section,
                'format' => 'excel',
                'filename' => $baseName.'.xlsx',
                'mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'encoding' => 'base64',
                'content' => base64_encode($this->buildXlsx($definition['label'] ?? $section, $rows, $columns)),
                'recordCount' => count($rows),
                'generatedAt' => now()->toISOString(),
                'filters' => $report['filters'] ?? $this->reportFilters($user, $section, $filters),
            ],
            'pdf', 'html', 'print' => [
                'section' => $section,
                'format' => 'pdf',
                'filename' => $baseName.'.html',
                'mimeType' => 'text/html; charset=UTF-8',
                'encoding' => 'utf-8',
                'content' => $this->buildHtmlReport($definition['label'] ?? $section, $report, $rows, $columns),
                'recordCount' => count($rows),
                'generatedAt' => now()->toISOString(),
                'filters' => $report['filters'] ?? $this->reportFilters($user, $section, $filters),
            ],
            default => [
                'section' => $section,
                'format' => 'csv',
                'filename' => $baseName.'.csv',
                'mimeType' => 'text/csv; charset=UTF-8',
                'encoding' => 'utf-8',
                'content' => $this->buildCsvReport($rows, $columns),
                'recordCount' => count($rows),
                'generatedAt' => now()->toISOString(),
                'filters' => $report['filters'] ?? $this->reportFilters($user, $section, $filters),
            ],
        };
    }

    public function reportFilters(User $user, string $section, array $filters = []): array
    {
        $academicYearId = $this->nullableInt($filters['academicYearId'] ?? null);
        $classId = $this->nullableInt($filters['classId'] ?? null);

        $academicYears = $this->academicLifecycle->academicYears()
            ->map(fn ($year): array => [
                'label' => $year->label ?: $year->code,
                'value' => (string) $year->id,
            ])
            ->values()
            ->all();

        $terms = $this->academicLifecycle->terms($academicYearId)
            ->map(fn ($term): array => [
                'label' => $term->label ?: $term->name,
                'value' => (string) $term->id,
            ])
            ->values()
            ->all();

        $classQuery = PreschoolClass::query()->whereNull('deleted_at')->orderBy('name');
        if ($user->role_code === 'teacher-preschool') {
            $allowedClassIds = $this->assessmentAggregation->accessibleClassIds($user);
            $classQuery->whereIn('id', $allowedClassIds);
        }
        if ($classId !== null) {
            $classQuery->where('id', $classId);
        }

        $classes = $classQuery->limit(50)->get()->map(fn (PreschoolClass $class): array => [
            'label' => $class->name,
            'value' => (string) $class->id,
        ])->values()->all();

        $students = PreschoolStudent::query()
            ->whereNull('deleted_at')
            ->when($classId !== null, function ($query) use ($classId): void {
                $query->whereHas('classes', fn ($relation) => $relation->where('preschool_classes.id', $classId));
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(50)
            ->get()
            ->map(fn (PreschoolStudent $student): array => [
                'label' => trim($student->first_name.' '.$student->last_name),
                'value' => (string) $student->id,
            ])
            ->values()
            ->all();

        $teachers = DB::table('users')
            ->whereNull('deleted_at')
            ->where('role_code', 'teacher-preschool')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(50)
            ->get()
            ->map(fn ($teacher): array => [
                'label' => trim(($teacher->first_name ?? '').' '.($teacher->last_name ?? '')),
                'value' => (string) $teacher->id,
            ])
            ->values()
            ->all();

        $reportPeriods = $this->reportPeriodService->reportPeriods($user)
            ->map(fn (array $period): array => [
                'label' => $period['label'] ?? '',
                'value' => (string) ($period['reportPeriodId'] ?? $period['id'] ?? ''),
            ])
            ->values()
            ->all();

        $statuses = match ($this->normalizeSection($section)) {
            'attendance' => [
                ['label' => 'All Statuses', 'value' => ''],
                ['label' => 'Present', 'value' => 'present'],
                ['label' => 'Absent', 'value' => 'absent'],
                ['label' => 'Late', 'value' => 'late'],
                ['label' => 'Excused', 'value' => 'excused'],
            ],
            'payments' => [
                ['label' => 'All Statuses', 'value' => ''],
                ['label' => 'Paid', 'value' => 'paid'],
                ['label' => 'Pending', 'value' => 'pending'],
                ['label' => 'Overdue', 'value' => 'overdue'],
                ['label' => 'Cancelled', 'value' => 'cancelled'],
            ],
            'guardians' => [
                ['label' => 'All Statuses', 'value' => ''],
                ['label' => 'Open', 'value' => 'open'],
                ['label' => 'Escalated', 'value' => 'escalated'],
                ['label' => 'Resolved', 'value' => 'resolved'],
                ['label' => 'Dismissed', 'value' => 'dismissed'],
            ],
            default => [
                ['label' => 'All Statuses', 'value' => ''],
            ],
        };

        return [
            'academicYears' => $academicYears,
            'terms' => $terms,
            'classes' => $classes,
            'students' => $students,
            'teachers' => $teachers,
            'reportPeriods' => $reportPeriods,
            'statuses' => $statuses,
            'exportFormats' => ['csv', 'excel', 'pdf'],
        ];
    }

    private function attendanceReport(User $user, array $filters, bool $includeMetadata = true): array
    {
        $query = $this->attendanceQuery($user, $filters);
        $records = $query->with(['student', 'preschoolClass'])->get();
        $total = $records->count();
        $present = $records->where('status', 'present')->count();
        $absent = $records->where('status', 'absent')->count();
        $late = $records->where('status', 'late')->count();
        $excused = $records->where('status', 'excused')->count();
        $average = $this->analytics->percentage($present, $total);

        $classBreakdown = $records
            ->groupBy('class_id')
            ->map(function (Collection $group): array {
                $class = $group->first()?->preschoolClass;
                $present = $group->where('status', 'present')->count();
                $total = $group->count();

                return [
                    'label' => $class?->name ?: 'Unknown class',
                    'value' => $present,
                    'total' => $total,
                    'attendanceRate' => $this->analytics->percentage($present, $total) ?? 0,
                    'absenceRate' => $this->analytics->percentage($group->where('status', 'absent')->count(), $total) ?? 0,
                    'lateRate' => $this->analytics->percentage($group->where('status', 'late')->count(), $total) ?? 0,
                ];
            })
            ->values()
            ->all();

        $trend = $this->trendByMonth($records, fn (PreschoolAttendanceRecord $record): string => (string) $record->attendance_date?->format('Y-m'));
        $rows = collect($classBreakdown)->map(fn (array $row): array => [
            'label' => $row['label'],
            'value' => $row['attendanceRate'] ?? 0,
            'total' => $row['total'] ?? 0,
        ])->values()->all();

        return $this->buildReportPayload('attendance', $user, $filters, [
            'summary' => [
                'attendanceCount' => $total,
                'presentCount' => $present,
                'absentCount' => $absent,
                'lateCount' => $late,
                'excusedCount' => $excused,
                'attendanceRate' => $average ?? 0,
                'absenceRate' => $this->analytics->percentage($absent, $total) ?? 0,
                'lateRate' => $this->analytics->percentage($late, $total) ?? 0,
            ],
            'cards' => $this->summaryCards([
                ['label' => 'Attendance Rate', 'value' => $average ?? 0, 'caption' => 'Present sessions'],
                ['label' => 'Absence Rate', 'value' => $this->analytics->percentage($absent, $total) ?? 0, 'caption' => 'Absent sessions'],
                ['label' => 'Late Rate', 'value' => $this->analytics->percentage($late, $total) ?? 0, 'caption' => 'Late sessions'],
            ]),
            'trend' => $trend,
            'classBreakdown' => $classBreakdown,
            'rows' => $rows,
            'table' => $rows,
        ], $includeMetadata);
    }

    private function assessmentReport(User $user, array $filters, bool $includeMetadata = true): array
    {
        $query = $this->assessmentQuery($user, $filters);
        $assessments = $query->with(['student', 'preschoolClass', 'category'])->get();
        $total = $assessments->count();
        $finalized = $assessments->where('status', 'finalized')->count();
        $average = $this->averageScore($assessments->pluck('score'));
        $classBreakdown = $assessments
            ->groupBy('class_id')
            ->map(function (Collection $group): array {
                $class = $group->first()?->preschoolClass;

                return [
                    'label' => $class?->name ?: 'Unknown class',
                    'value' => $this->averageScore($group->pluck('score')) ?? 0,
                    'count' => $group->count(),
                ];
            })
            ->values()
            ->all();

        $periodPerformance = $assessments
            ->groupBy('period_label')
            ->map(function (Collection $group, string $period): array {
                return [
                    'label' => $period ?: 'No period',
                    'value' => $this->averageScore($group->pluck('score')) ?? 0,
                    'count' => $group->count(),
                ];
            })
            ->values()
            ->all();

        $categoryPerformance = $assessments
            ->groupBy('category_id')
            ->map(function (Collection $group): array {
                $category = $group->first()?->category;

                return [
                    'label' => $category?->name ?: 'Unknown category',
                    'value' => $this->averageScore($group->pluck('score')) ?? 0,
                    'count' => $group->count(),
                ];
            })
            ->values()
            ->all();

        $trend = $this->trendByMonth($assessments, fn (PreschoolStudentAssessment $assessment): string => (string) $assessment->assessment_date?->format('Y-m'));
        $rows = $periodPerformance;

        return $this->buildReportPayload('assessments', $user, $filters, [
            'summary' => [
                'completionRate' => $this->analytics->percentage($finalized, $total) ?? 0,
                'finalizedAssessments' => $finalized,
                'averageScore' => $average ?? 0,
                'assessmentCount' => $total,
            ],
            'cards' => $this->summaryCards([
                ['label' => 'Completion', 'value' => $this->analytics->percentage($finalized, $total) ?? 0, 'caption' => 'Finalized assessments'],
                ['label' => 'Average Score', 'value' => $average ?? 0, 'caption' => 'Overall average'],
                ['label' => 'Assessment Count', 'value' => $total, 'caption' => 'Total records'],
            ]),
            'trend' => $trend,
            'performance' => $categoryPerformance,
            'classBreakdown' => $classBreakdown,
            'table' => $rows,
            'rows' => $rows,
        ], $includeMetadata);
    }

    private function healthReport(User $user, array $filters, bool $includeMetadata = true): array
    {
        $query = $this->healthQuery($user, $filters);
        $alerts = $query->with(['student'])->get();
        $total = $alerts->count();
        $open = $alerts->whereIn('status', ['open', 'acknowledged'])->count();
        $critical = $alerts->where('severity', 'critical')->count();
        $openCritical = $alerts
            ->whereIn('status', ['open', 'acknowledged'])
            ->where('severity', 'critical')
            ->count();
        $high = $alerts->where('severity', 'high')->count();
        $severityDistribution = $alerts->groupBy('severity')->map(fn (Collection $group, string $severity): array => [
            'label' => ucfirst($severity ?: 'unknown'),
            'value' => $group->count(),
        ])->values()->all();

        $trend = $this->trendByMonth($alerts, fn (PreschoolHealthAlert $alert): string => (string) $alert->created_at?->format('Y-m'));
        $rows = $severityDistribution;

        return $this->buildReportPayload('health', $user, $filters, [
            'summary' => [
                'activeAlerts' => $open,
                'incidents' => $total,
                'criticalAlerts' => $critical,
                'openCriticalAlerts' => $openCritical,
                'highAlerts' => $high,
                'allergies' => PreschoolStudentAllergy::query()->count(),
                'medications' => PreschoolStudentMedicationRecord::query()->count(),
                'vaccinations' => PreschoolStudentVaccinationRecord::query()->count(),
            ],
            'cards' => $this->summaryCards([
                ['label' => 'Active Alerts', 'value' => $open, 'caption' => 'Open / acknowledged'],
                ['label' => 'Critical', 'value' => $critical, 'caption' => 'Critical alerts'],
                ['label' => 'Medications', 'value' => PreschoolStudentMedicationRecord::query()->count(), 'caption' => 'Medication records'],
            ]),
            'trend' => $trend,
            'alerts' => $severityDistribution,
            'incidents' => $rows,
            'rows' => $rows,
        ], $includeMetadata);
    }

    private function billingReport(User $user, array $filters, bool $includeMetadata = true): array
    {
        $query = $this->billingQuery($user, $filters);
        $invoices = $query->with(['student', 'preschoolClass'])->get();
        $total = $invoices->count();
        $paid = $invoices->where('status', 'paid')->count();
        $overdue = $invoices->where('status', 'overdue')->count();
        $issued = $total;
        $receipts = PreschoolReceipt::query()->count();
        $revenue = $invoices->sum(fn (PreschoolInvoice $invoice): float => (float) $invoice->paid_amount);
        $outstandingBalance = $invoices->sum(fn (PreschoolInvoice $invoice): float => (float) $invoice->balance_due);

        $trend = $this->trendByMonth($invoices, fn (PreschoolInvoice $invoice): string => (string) $invoice->issue_date?->format('Y-m'));
        $rows = $trend;

        return $this->buildReportPayload('payments', $user, $filters, [
            'summary' => [
                'invoicesIssued' => $issued,
                'invoicesPaid' => $paid,
                'invoicesOverdue' => $overdue,
                'receiptsGenerated' => $receipts,
                'revenue' => round($revenue, 2),
                'outstandingBalance' => round($outstandingBalance, 2),
            ],
            'cards' => $this->summaryCards([
                ['label' => 'Revenue', 'value' => round($revenue, 2), 'caption' => 'Paid amount'],
                ['label' => 'Overdue', 'value' => $overdue, 'caption' => 'Outstanding invoices'],
                ['label' => 'Receipts', 'value' => $receipts, 'caption' => 'Generated receipts'],
            ]),
            'trend' => $trend,
            'revenue' => $rows,
            'outstanding' => $this->statusRows($invoices, 'status', 'overdue'),
            'overdue' => $this->statusRows($invoices, 'status', 'overdue'),
            'rows' => $rows,
        ], $includeMetadata);
    }

    private function enrollmentReport(User $user, array $filters, bool $includeMetadata = true): array
    {
        $query = $this->enrollmentQuery($user, $filters);
        $applications = $query->with(['requestedAcademicYear', 'requestedTerm', 'preferredClass'])->get();
        $submitted = $applications->whereIn('status', ['submitted', 'under_review'])->count();
        $accepted = $applications->where('status', 'approved')->count();
        $rejected = $applications->where('status', 'rejected')->count();
        $withdrawn = $applications->where('status', 'cancelled')->count();
        $enrolled = $applications->where('status', 'enrolled')->count();

        $trend = $this->trendByMonth($applications, fn (PreschoolEnrollmentApplication $application): string => (string) $application->application_date?->format('Y-m'));
        $rows = $trend;

        return $this->buildReportPayload('enrollments', $user, $filters, [
            'summary' => [
                'applicationsSubmitted' => $submitted,
                'accepted' => $accepted,
                'rejected' => $rejected,
                'withdrawn' => $withdrawn,
                'enrolled' => $enrolled,
                'conversionRate' => $this->analytics->percentage($enrolled, max($submitted, 1)) ?? 0,
            ],
            'cards' => $this->summaryCards([
                ['label' => 'Submitted', 'value' => $submitted, 'caption' => 'Applications submitted'],
                ['label' => 'Enrolled', 'value' => $enrolled, 'caption' => 'Successful enrollments'],
                ['label' => 'Conversion', 'value' => $this->analytics->percentage($enrolled, max($submitted, 1)) ?? 0, 'caption' => 'Enrollment conversion'],
            ]),
            'trend' => $trend,
            'admissions' => $rows,
            'rows' => $rows,
        ], $includeMetadata);
    }

    private function guardianReport(User $user, array $filters, bool $includeMetadata = true): array
    {
        $query = $this->guardianQuery($user, $filters);
        $issues = $query->with(['student', 'guardian'])->get();
        $open = $issues->where('status', 'open')->count();
        $recurring = $issues->sum('recurrence_count');
        $stale = $issues->filter(fn (PreschoolGuardianGovernanceIssue $issue): bool => $issue->status === 'open' && $issue->detected_at?->lt(now()->subDays(14)))->count();
        $resolutionMinutes = $issues->filter(fn (PreschoolGuardianGovernanceIssue $issue): bool => $issue->resolved_at !== null && $issue->detected_at !== null)
            ->map(fn (PreschoolGuardianGovernanceIssue $issue): int => $issue->detected_at->diffInMinutes($issue->resolved_at))
            ->average();

        $trend = $this->trendByMonth($issues, fn (PreschoolGuardianGovernanceIssue $issue): string => (string) $issue->detected_at?->format('Y-m'));
        $rows = $issues->groupBy('issue_type')->map(fn (Collection $group, string $type): array => [
            'label' => ucfirst(str_replace('_', ' ', $type ?: 'issue')),
            'value' => $group->count(),
        ])->values()->all();

        return $this->buildReportPayload('guardians', $user, $filters, [
            'summary' => [
                'activeIssues' => $open,
                'recurringIssues' => $recurring,
                'staleIssues' => $stale,
                'resolutionTimeMinutes' => round((float) ($resolutionMinutes ?? 0), 2),
                'escalations' => $issues->where('severity', 'critical')->count(),
            ],
            'cards' => $this->summaryCards([
                ['label' => 'Active Issues', 'value' => $open, 'caption' => 'Open governance issues'],
                ['label' => 'Recurring', 'value' => $recurring, 'caption' => 'Repeat occurrences'],
                ['label' => 'Stale', 'value' => $stale, 'caption' => 'Older unresolved issues'],
            ]),
            'trend' => $trend,
            'issues' => $rows,
            'communications' => $this->trendByMonth(PreschoolGuardianCommunication::query()->get(), fn (PreschoolGuardianCommunication $item): string => (string) $item->created_at?->format('Y-m')),
            'rows' => $rows,
        ], $includeMetadata);
    }

    private function classroomReport(User $user, array $filters): array
    {
        $classId = $this->nullableInt($filters['classId'] ?? null);
        $periodLabel = trim((string) ($filters['periodLabel'] ?? $filters['reportPeriodLabel'] ?? ''));

        $class = $classId ? PreschoolClass::query()->find($classId) : null;
        if (! $class) {
            $class = PreschoolClass::query()->whereNull('deleted_at')->orderBy('name')->first();
        }

        if (! $class) {
            return $this->buildEmptyReport('classroom', $user, $filters);
        }

        $bundle = $this->classroomReportService->bundle($user, $class, $periodLabel !== '' ? $periodLabel : null);
        $report = $bundle['report'] ?? null;

        return $this->buildReportPayload('classroom', $user, $filters, [
            'summary' => $report['summary'] ?? [],
            'cards' => $this->summaryCards([
                ['label' => 'Average Score', 'value' => $report['summary']['averageScore'] ?? 0, 'caption' => $class->name],
                ['label' => 'Assessments', 'value' => $report['summary']['finalizedAssessments'] ?? 0, 'caption' => 'Finalized assessments'],
            ]),
            'trend' => $report['categorySummaries'] ?? [],
            'rows' => $report['observations'] ?? [],
            'table' => $report['assessments'] ?? [],
        ]);
    }

    private function operationsReport(User $user, array $filters): array
    {
        // Child reports are internal aggregates here; only the final dashboard needs filter metadata.
        $attendance = $this->attendanceReport($user, $filters, false);
        $assessments = $this->assessmentReport($user, $filters, false);
        $health = $this->healthReport($user, $filters, false);
        $billing = $this->billingReport($user, $filters, false);
        $enrollment = $this->enrollmentReport($user, $filters, false);
        $guardians = $this->guardianReport($user, $filters, false);

        $comparisonFilters = Arr::except($filters, ['dateFrom', 'dateTo', 'reportPeriodId', 'status']);
        $today = today();
        $yesterday = $today->copy()->subDay();
        $monthStart = $today->copy()->startOfMonth();

        $todayAttendance = $this->attendanceQuery($user, array_merge($comparisonFilters, [
            'dateFrom' => $today->toDateString(),
            'dateTo' => $today->toDateString(),
        ]))->get();
        $yesterdayAttendance = $this->attendanceQuery($user, array_merge($comparisonFilters, [
            'dateFrom' => $yesterday->toDateString(),
            'dateTo' => $yesterday->toDateString(),
        ]))->get();

        $attendanceToday = $this->analytics->percentage(
            $todayAttendance->where('status', 'present')->count(),
            $todayAttendance->count(),
        );
        $attendanceYesterday = $this->analytics->percentage(
            $yesterdayAttendance->where('status', 'present')->count(),
            $yesterdayAttendance->count(),
        );
        $attendanceExceptionsToday = $todayAttendance->whereIn('status', ['absent', 'late'])->count();

        $activeStudents = PreschoolStudent::query()->whereNull('deleted_at')->where('status', 'active')->count();
        $activeStudentsAtMonthStart = PreschoolStudent::query()
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->where('created_at', '<', $monthStart)
            ->count();

        $previousOpenHealthAlerts = $this->healthQuery($user, $comparisonFilters)
            ->where('created_at', '<=', $yesterday->copy()->endOfDay())
            ->where(function ($query) use ($yesterday): void {
                $query->whereNull('resolved_at')->orWhere('resolved_at', '>', $yesterday->copy()->endOfDay());
            })
            ->where(function ($query) use ($yesterday): void {
                $query->whereNull('closed_at')->orWhere('closed_at', '>', $yesterday->copy()->endOfDay());
            })
            ->count();

        $previousPendingEnrollments = $this->enrollmentQuery($user, $comparisonFilters)
            ->whereIn('status', ['submitted', 'under_review'])
            ->where('application_date', '<', $monthStart->toDateString())
            ->count();

        $previousOutstandingBalance = (float) $this->billingQuery($user, $comparisonFilters)
            ->where('issue_date', '<', $monthStart->toDateString())
            ->sum('balance_due');

        $summary = [
            'totalStudents' => PreschoolStudent::query()->whereNull('deleted_at')->count(),
            'activeStudents' => $activeStudents,
            'newEnrollments' => $enrollment['summary']['applicationsSubmitted'] ?? 0,
            'attendanceRate' => $attendance['summary']['attendanceRate'] ?? 0,
            'assessmentCompletion' => $assessments['summary']['completionRate'] ?? 0,
            'revenue' => $billing['summary']['revenue'] ?? 0,
            'openHealthAlerts' => $health['summary']['activeAlerts'] ?? 0,
            'openGuardianIssues' => $guardians['summary']['activeIssues'] ?? 0,
            'overdueInvoices' => $billing['summary']['invoicesOverdue'] ?? 0,
            'outstandingBalance' => $billing['summary']['outstandingBalance'] ?? 0,
        ];

        $analytics = [
            'activeStudents' => $this->analytics->comparison($activeStudents, $activeStudentsAtMonthStart, 'start_of_month'),
            'attendanceToday' => $this->analytics->comparison($attendanceToday, $attendanceYesterday, 'previous_day'),
            'openHealthAlerts' => $this->analytics->comparison($summary['openHealthAlerts'], $previousOpenHealthAlerts, 'previous_day'),
            'pendingEnrollments' => $this->analytics->comparison($summary['newEnrollments'], $previousPendingEnrollments, 'start_of_month'),
            'outstandingPayments' => $this->analytics->comparison($summary['outstandingBalance'], round($previousOutstandingBalance, 2), 'start_of_month'),
        ];

        $executiveHealth = [
            'enrollment' => [
                'status' => $this->analytics->healthStatus($summary['newEnrollments'], 1, PHP_INT_MAX),
                'value' => $summary['newEnrollments'],
            ],
            'attendance' => [
                'status' => $attendanceToday === null
                    ? PreschoolAnalyticsService::NEUTRAL
                    : $this->analytics->worstHealthStatus([
                        $this->analytics->healthStatus($attendanceToday, 90, 80, false),
                        $this->analytics->healthStatus($attendanceExceptionsToday, 1, PHP_INT_MAX),
                    ]),
                'value' => $attendanceToday,
                'exceptions' => $attendanceExceptionsToday,
            ],
            'billing' => [
                'status' => $this->analytics->healthStatus($summary['outstandingBalance'], 0.01, PHP_INT_MAX),
                'value' => $summary['outstandingBalance'],
            ],
            'assessment' => [
                'status' => $this->analytics->healthStatus($summary['assessmentCompletion'], 85, 70, false),
                'value' => $summary['assessmentCompletion'],
            ],
            'health' => [
                'status' => $this->analytics->worstHealthStatus([
                    $this->analytics->healthStatus($summary['openHealthAlerts'], 1, 4),
                    $this->analytics->healthStatus($health['summary']['openCriticalAlerts'] ?? 0, 1, 1),
                ]),
                'value' => $summary['openHealthAlerts'],
                'critical' => $health['summary']['openCriticalAlerts'] ?? 0,
            ],
            'guardians' => [
                'status' => $this->analytics->healthStatus($summary['openGuardianIssues'], 1, 3),
                'value' => $summary['openGuardianIssues'],
            ],
        ];

        return $this->buildReportPayload('operations', $user, $filters, [
            'summary' => $summary,
            'kpis' => [
                'attendanceRate' => $summary['attendanceRate'],
                'activeStudents' => $summary['activeStudents'],
                'attendanceToday' => $attendanceToday,
                'revenue' => $summary['revenue'],
                'openHealthAlerts' => $summary['openHealthAlerts'],
                'criticalHealthAlerts' => $health['summary']['openCriticalAlerts'] ?? 0,
                'assessmentCompletion' => $summary['assessmentCompletion'],
                'pendingEnrollments' => $summary['newEnrollments'],
                'outstandingBalances' => $summary['outstandingBalance'],
                'overdueInvoices' => $summary['overdueInvoices'],
                'openGuardianIssues' => $summary['openGuardianIssues'],
            ],
            'analytics' => $analytics,
            'executiveHealth' => $executiveHealth,
            'modules' => [
                'attendance' => [
                    'attendance_rate' => $attendance['summary']['attendanceRate'] ?? 0,
                    'absence_rate' => $attendance['summary']['absenceRate'] ?? 0,
                    'late_rate' => $attendance['summary']['lateRate'] ?? 0,
                ],
                'assessments' => [
                    'completion_rate' => $assessments['summary']['completionRate'] ?? 0,
                    'average_score' => $assessments['summary']['averageScore'] ?? 0,
                ],
                'health' => [
                    'open_alerts' => $health['summary']['activeAlerts'] ?? 0,
                    'critical_alerts' => $health['summary']['criticalAlerts'] ?? 0,
                ],
                'payments' => [
                    'revenue' => $billing['summary']['revenue'] ?? 0,
                    'overdue_invoices' => $billing['summary']['invoicesOverdue'] ?? 0,
                ],
                'enrollments' => [
                    'new_enrollments' => $enrollment['summary']['applicationsSubmitted'] ?? 0,
                    'conversion_rate' => $enrollment['summary']['conversionRate'] ?? 0,
                ],
                'guardians' => [
                    'open_issues' => $guardians['summary']['activeIssues'] ?? 0,
                    'recurring_issues' => $guardians['summary']['recurringIssues'] ?? 0,
                ],
            ],
            'cards' => $this->summaryCards([
                ['label' => 'Attendance', 'value' => $attendance['summary']['attendanceRate'] ?? 0, 'caption' => 'Attendance rate'],
                ['label' => 'Assessments', 'value' => $assessments['summary']['completionRate'] ?? 0, 'caption' => 'Completion rate'],
                ['label' => 'Billing', 'value' => $billing['summary']['revenue'] ?? 0, 'caption' => 'Revenue'],
                ['label' => 'Health', 'value' => $health['summary']['activeAlerts'] ?? 0, 'caption' => 'Open alerts'],
            ]),
            'trend' => $attendance['trend'] ?? [],
            'risk' => [
                'healthAlerts' => $health['summary']['activeAlerts'] ?? 0,
                'guardianIssues' => $guardians['summary']['activeIssues'] ?? 0,
                'overdueInvoices' => $billing['summary']['invoicesOverdue'] ?? 0,
            ],
            'rows' => $attendance['classBreakdown'] ?? [],
        ]);
    }

    private function complianceReport(User $user, array $filters): array
    {
        $auditQuery = PreschoolLifecycleAuditLog::query();
        $exportQuery = PreschoolReportPeriod::query();

        $timeline = $auditQuery
            ->whereDate('created_at', '>=', now()->subDays(90)->toDateString())
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn (PreschoolLifecycleAuditLog $log): array => [
                'label' => $log->action_type,
                'value' => 1,
                'raw' => [
                    'actionType' => $log->action_type,
                    'entityType' => $log->entity_type,
                    'entityId' => $log->entity_id,
                    'createdAt' => $log->created_at?->toISOString(),
                ],
            ])
            ->values()
            ->all();

        return $this->buildReportPayload('compliance', $user, $filters, [
            'summary' => [
                'auditEvents' => PreschoolLifecycleAuditLog::query()->count(),
                'reportPeriods' => $exportQuery->count(),
                'snapshots' => PreschoolReportSnapshot::query()->count(),
                'exports' => $this->exportGovernanceService->overview([])['totalExports'] ?? 0,
            ],
            'cards' => $this->summaryCards([
                ['label' => 'Audit Events', 'value' => PreschoolLifecycleAuditLog::query()->count(), 'caption' => 'Lifecycle actions'],
                ['label' => 'Snapshots', 'value' => PreschoolReportSnapshot::query()->count(), 'caption' => 'Immutable report history'],
                ['label' => 'Exports', 'value' => $this->exportGovernanceService->overview([])['totalExports'] ?? 0, 'caption' => 'Generated exports'],
            ]),
            'timeline' => $timeline,
            'rows' => $timeline,
        ]);
    }

    private function buildReportPayload(
        string $section,
        User $user,
        array $filters,
        array $payload,
        bool $includeMetadata = true,
    ): array
    {
        $definition = $this->definition($section);
        $filterOptions = $includeMetadata ? $this->reportFilters($user, $section, $filters) : [];
        $generatedAt = now()->toISOString();

        return array_merge([
            'report' => $section,
            'section' => $section,
            'summary' => [],
            'cards' => [],
            'trend' => [],
            'performance' => [],
            'completion' => [],
            'classBreakdown' => [],
            'studentBreakdown' => [],
            'rows' => [],
            'table' => [],
            'incidents' => [],
            'alerts' => [],
            'vaccinations' => [],
            'revenue' => [],
            'outstanding' => [],
            'overdue' => [],
            'admissions' => [],
            'issues' => [],
            'communications' => [],
            'modules' => [],
            'risk' => [],
            'exportFormats' => $definition['exportFormats'] ?? ['csv', 'excel', 'pdf'],
            'generatedAt' => $generatedAt,
            'filters' => $filterOptions,
        ], $payload);
    }

    private function buildEmptyReport(string $section, User $user, array $filters): array
    {
        return $this->buildReportPayload($section, $user, $filters, [
            'summary' => [],
            'cards' => [],
        ]);
    }

    private function definition(string $section): array
    {
        return $this->definitions()[ $this->normalizeSection($section) ] ?? $this->definitions()['operations'];
    }

    private function normalizeSection(string $section): string
    {
        $section = strtolower(trim($section));

        return match ($section) {
            'assessment' => 'assessments',
            'billing', 'payment' => 'payments',
            'enrollment', 'enrollments' => 'enrollment',
            'guardian', 'guardians' => 'guardians',
            'attendance' => 'attendance',
            'health' => 'health',
            'classroom' => 'classroom',
            'operations', 'dashboard' => 'operations',
            'compliance' => 'compliance',
            default => $section ?: 'operations',
        };
    }

    private function summaryCards(array $cards): array
    {
        return array_map(static fn (array $card): array => [
            'title' => $card['label'] ?? '',
            'value' => $card['value'] ?? 0,
            'caption' => $card['caption'] ?? '',
            'tone' => $card['tone'] ?? 'info',
        ], $cards);
    }

    private function exportRows(array $report, string $rowsKey): array
    {
        $rows = Arr::get($report, $rowsKey, Arr::get($report, 'rows', Arr::get($report, 'table', [])));

        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_map(function (array $row): array {
            return [
                'label' => trim((string) ($row['label'] ?? $row['name'] ?? $row['title'] ?? '')),
                'value' => $row['value'] ?? $row['count'] ?? $row['score'] ?? 0,
            ];
        }, $rows));
    }

    private function buildCsvReport(array $rows, array $columns): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, array_map(static fn (array $column): string => (string) ($column['label'] ?? $column['key'] ?? ''), $columns));

        foreach ($rows as $row) {
            fputcsv($stream, array_map(static fn (array $column) => $row[$column['key']] ?? '', $columns));
        }

        rewind($stream);
        $csv = stream_get_contents($stream) ?: '';
        fclose($stream);

        return $csv;
    }

    private function buildHtmlReport(string $title, array $report, array $rows, array $columns): string
    {
        $summaryRows = '';
        foreach ((array) ($report['summary'] ?? []) as $key => $value) {
            $summaryRows .= '<tr><th style="text-align:left;padding:6px 8px;border-bottom:1px solid #e2e8f0;">'.htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8').'</th><td style="padding:6px 8px;border-bottom:1px solid #e2e8f0;">'.htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8').'</td></tr>';
        }

        $tableRows = '';
        foreach ($rows as $row) {
            $tableRows .= '<tr>';
            foreach ($columns as $column) {
                $tableRows .= '<td style="padding:6px 8px;border-bottom:1px solid #e2e8f0;">'.htmlspecialchars((string) ($row[$column['key']] ?? ''), ENT_QUOTES, 'UTF-8').'</td>';
            }
            $tableRows .= '</tr>';
        }

        $headers = '';
        foreach ($columns as $column) {
            $headers .= '<th style="text-align:left;padding:8px;border-bottom:1px solid #cbd5e1;">'.htmlspecialchars((string) ($column['label'] ?? $column['key'] ?? ''), ENT_QUOTES, 'UTF-8').'</th>';
        }

        return '<!doctype html><html><head><meta charset="utf-8"><title>'
            .htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
            .'</title><style>body{font-family:Arial,sans-serif;color:#0f172a;padding:24px}table{border-collapse:collapse;width:100%}h1,h2{margin:0 0 12px}</style></head><body>'
            .'<h1>'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'</h1>'
            .'<p>Generated at: '.htmlspecialchars(now()->toISOString(), ENT_QUOTES, 'UTF-8').'</p>'
            .'<h2>Summary</h2><table>'.$summaryRows.'</table>'
            .'<h2 style="margin-top:24px">Details</h2><table><thead><tr>'.$headers.'</tr></thead><tbody>'.$tableRows.'</tbody></table>'
            .'</body></html>';
    }

    private function buildXlsx(string $title, array $rows, array $columns): string
    {
        $sheetRows = [];
        $sheetRows[] = '<row r="1">'.implode('', array_map(function (array $column, int $index): string {
            $cell = $this->columnLetter($index + 1).'1';
            return '<c r="'.$cell.'" t="inlineStr"><is><t>'.htmlspecialchars((string) ($column['label'] ?? $column['key'] ?? ''), ENT_QUOTES, 'UTF-8').'</t></is></c>';
        }, $columns, array_keys($columns))).'</row>';

        foreach ($rows as $rowIndex => $row) {
            $cells = [];
            foreach ($columns as $columnIndex => $column) {
                $cell = $this->columnLetter($columnIndex + 1).($rowIndex + 2);
                $value = $row[$column['key']] ?? '';
                $cells[] = '<c r="'.$cell.'" t="inlineStr"><is><t>'.htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8').'</t></is></c>';
            }
            $sheetRows[] = '<row r="'.($rowIndex + 2).'">'.implode('', $cells).'</row>';
        }

        $sheetXml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>'.implode('', $sheetRows).'</sheetData>'
            .'</worksheet>';

        $workbookXml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';

        $stylesXml = '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="1"><font><sz val="11"/><name val="Arial"/></font></fonts><fills count="1"><fill><patternFill patternType="none"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs></styleSheet>';

        $contentTypesXml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';

        $rootRels = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';

        $workbookRels = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';

        return $this->buildZipArchive([
            '[Content_Types].xml' => $contentTypesXml,
            '_rels/.rels' => $rootRels,
            'xl/workbook.xml' => $workbookXml,
            'xl/_rels/workbook.xml.rels' => $workbookRels,
            'xl/styles.xml' => $stylesXml,
            'xl/worksheets/sheet1.xml' => $sheetXml,
        ]);
    }

    private function buildZipArchive(array $entries): string
    {
        $files = [];
        $offset = 0;

        foreach ($entries as $name => $content) {
            $binary = (string) $content;
            $compressed = function_exists('gzdeflate') ? gzdeflate($binary, 9) : false;
            if ($compressed === false) {
                $compressed = $binary;
                $method = 0;
            } else {
                $method = 8;
            }

            $crc = sprintf('%u', crc32($binary));
            $compressedSize = strlen($compressed);
            $uncompressedSize = strlen($binary);

            $localHeader = pack(
                'VvvvvvVVVvv',
                0x04034b50,
                20,
                0,
                $method,
                0,
                0,
                (int) $crc,
                $compressedSize,
                $uncompressedSize,
                strlen($name),
                0,
            ).$name.$compressed;

            $files[] = [
                'name' => $name,
                'method' => $method,
                'crc' => (int) $crc,
                'compressed_size' => $compressedSize,
                'uncompressed_size' => $uncompressedSize,
                'offset' => $offset,
                'data' => $localHeader,
            ];

            $offset += strlen($localHeader);
        }

        $centralDirectory = '';
        foreach ($files as $file) {
            $centralDirectory .= pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,
                20,
                20,
                0,
                $file['method'],
                0,
                0,
                $file['crc'],
                $file['compressed_size'],
                $file['uncompressed_size'],
                strlen($file['name']),
                0,
                0,
                0,
                0,
                0,
                $file['offset'],
            ).$file['name'];
        }

        $centralDirectorySize = strlen($centralDirectory);
        $centralDirectoryOffset = $offset;

        $zip = '';
        foreach ($files as $file) {
            $zip .= $file['data'];
        }
        $zip .= $centralDirectory;
        $zip .= pack(
            'VvvvvVVv',
            0x06054b50,
            0,
            0,
            count($files),
            count($files),
            $centralDirectorySize,
            $centralDirectoryOffset,
            0,
        );

        return $zip;
    }

    private function attendanceQuery(User $user, array $filters)
    {
        $query = PreschoolAttendanceRecord::query()->with(['preschoolClass']);
        $this->applyCommonFilters($query, $filters, 'attendance_date', [
            'academicYearId' => 'academic_year_id',
            'termId' => 'term_id',
            'studentId' => 'student_id',
            'classId' => 'class_id',
        ]);
        $this->applyClassScope($query, $user, $filters);

        return $query;
    }

    private function assessmentQuery(User $user, array $filters)
    {
        $query = PreschoolStudentAssessment::query()->with(['preschoolClass', 'category']);
        $this->applyCommonFilters($query, $filters, 'assessment_date', [
            'academicYearId' => 'academic_year_id',
            'termId' => 'term_id',
            'studentId' => 'student_id',
            'classId' => 'class_id',
        ]);
        $this->applyClassScope($query, $user, $filters);

        if (($filters['status'] ?? '') !== '') {
            $query->where('status', $filters['status']);
        }

        return $query;
    }

    private function healthQuery(User $user, array $filters)
    {
        $query = PreschoolHealthAlert::query()->with(['student']);
        $this->applyCommonFilters($query, $filters, 'created_at', [
            'studentId' => 'student_id',
        ]);
        $this->applyStudentScope($query, $user, $filters);

        return $query;
    }

    private function billingQuery(User $user, array $filters)
    {
        $query = PreschoolInvoice::query()->with(['student', 'preschoolClass']);
        $this->applyCommonFilters($query, $filters, 'issue_date', [
            'academicYearId' => 'academic_year_id',
            'termId' => 'term_id',
            'studentId' => 'student_id',
            'classId' => 'class_id',
        ]);
        $this->applyClassScope($query, $user, $filters);
        if (($filters['status'] ?? '') !== '') {
            $query->where('status', $filters['status']);
        }

        return $query;
    }

    private function enrollmentQuery(User $user, array $filters)
    {
        $query = PreschoolEnrollmentApplication::query()->with(['preferredClass']);
        $this->applyCommonFilters($query, $filters, 'application_date', [
            'academicYearId' => 'requested_academic_year_id',
            'termId' => 'requested_term_id',
            'classId' => 'preferred_class_id',
        ]);
        $this->applyClassScope($query, $user, $filters, 'preferred_class_id', 'preferredClass');
        if (($filters['status'] ?? '') !== '') {
            $query->where('status', $filters['status']);
        }

        return $query;
    }

    private function guardianQuery(User $user, array $filters)
    {
        $query = PreschoolGuardianGovernanceIssue::query()->with(['student']);
        $this->applyCommonFilters($query, $filters, 'detected_at', [
            'studentId' => 'student_id',
        ]);
        $this->applyStudentScope($query, $user, $filters);
        if (($filters['status'] ?? '') !== '') {
            $query->where('status', $filters['status']);
        }

        return $query;
    }

    private function applyCommonFilters($query, array $filters, string $dateColumn, array $columnMap = []): void
    {
        $academicYearId = $this->nullableInt($filters['academicYearId'] ?? null);
        $termId = $this->nullableInt($filters['termId'] ?? null);
        $studentId = $this->nullableInt($filters['studentId'] ?? null);
        $classId = $this->nullableInt($filters['classId'] ?? null);
        $dateFrom = $this->nullableDate($filters['dateFrom'] ?? null);
        $dateTo = $this->nullableDate($filters['dateTo'] ?? null);
        $reportPeriodId = $this->nullableInt($filters['reportPeriodId'] ?? null);

        $academicYearColumn = $columnMap['academicYearId'] ?? null;
        if ($academicYearId !== null && $academicYearColumn) {
            $query->where($academicYearColumn, $academicYearId);
        }

        $termColumn = $columnMap['termId'] ?? null;
        if ($termId !== null && $termColumn) {
            $query->where($termColumn, $termId);
        }

        $studentColumn = $columnMap['studentId'] ?? null;
        if ($studentId !== null && $studentColumn) {
            $query->where($studentColumn, $studentId);
        }

        $classColumn = $columnMap['classId'] ?? null;
        if ($classId !== null && $classColumn) {
            $query->where($classColumn, $classId);
        }

        if ($reportPeriodId !== null) {
            $period = PreschoolReportPeriod::query()->find($reportPeriodId);
            if ($period && $dateColumn !== 'created_at') {
                $dateFrom = $dateFrom ?: $period->from_date?->toDateString();
                $dateTo = $dateTo ?: $period->to_date?->toDateString();
            }
        }

        if ($dateFrom !== null) {
            $query->whereDate($dateColumn, '>=', $dateFrom);
        }

        if ($dateTo !== null) {
            $query->whereDate($dateColumn, '<=', $dateTo);
        }
    }

    private function applyClassScope($query, User $user, array $filters, string $classColumn = 'class_id', string $relationName = 'preschoolClass'): void
    {
        $classId = $this->nullableInt($filters['classId'] ?? null);
        $teacherId = $this->nullableText($filters['teacherId'] ?? null);
        $allowedClassIds = $this->assessmentAggregation->accessibleClassIds($user);

        if ($user->role_code === 'teacher-preschool') {
            $query->whereIn($classColumn, $allowedClassIds);
        }

        if ($classId !== null) {
            $query->where($classColumn, $classId);
        }

        if ($teacherId !== null) {
            $query->whereHas($relationName, fn ($relation) => $relation->where('teacher_user_id', $teacherId));
        }
    }

    private function applyStudentScope($query, User $user, array $filters, string $studentColumn = 'student_id'): void
    {
        $studentId = $this->nullableInt($filters['studentId'] ?? null);

        if ($studentId !== null) {
            $query->where($studentColumn, $studentId);
        }
    }

    private function trendByMonth(Collection $items, callable $dateResolver): array
    {
        return $items
            ->groupBy(function ($item) use ($dateResolver): string {
                return trim((string) $dateResolver($item)) ?: 'unknown';
            })
            ->map(function (Collection $group, string $bucket): array {
                return [
                    'label' => $bucket,
                    'value' => $group->count(),
                ];
            })
            ->values()
            ->all();
    }

    private function averageScore(Collection $scores): ?float
    {
        $values = $scores->filter(static fn ($score): bool => $score !== null && $score !== '')->map(static fn ($score): float => (float) $score);

        if ($values->isEmpty()) {
            return null;
        }

        return round((float) $values->avg(), 2);
    }

    private function statusRows(Collection $items, string $field, string $status): array
    {
        return $items
            ->filter(fn ($item): bool => (string) data_get($item, $field) === $status)
            ->map(fn ($item): array => [
                'label' => trim((string) data_get($item, 'invoice_number', data_get($item, 'receipt_number', data_get($item, 'payment_reference', 'Record')))),
                'value' => 1,
            ])
            ->values()
            ->all();
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }

    private function nullableDate(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : Carbon::parse($text)->toDateString();
    }

    private function columnLetter(int $column): string
    {
        $letters = '';
        while ($column > 0) {
            $remainder = ($column - 1) % 26;
            $letters = chr(65 + $remainder).$letters;
            $column = intdiv($column - 1, 26);
        }

        return $letters;
    }
}
