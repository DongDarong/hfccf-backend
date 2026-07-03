<?php

namespace App\Support;

use App\Models\User;
use App\Services\PreschoolAutomationTaskService;
use App\Services\PreschoolGuardianCommunicationService;
use App\Services\PreschoolNotificationService;
use App\Services\PreschoolWorkflowService;
use App\Support\PreschoolReporting\PreschoolReportingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class PreschoolOperationsService
{
    public function __construct(
        private readonly PreschoolReportingService $reportingService,
        private readonly PreschoolAnalyticsService $analyticsService,
        private readonly PreschoolAttendanceSessionService $attendanceSessionService,
        private readonly PreschoolAttendanceAlertService $attendanceAlertService,
        private readonly PreschoolGuardianCommunicationService $guardianCommunicationService,
        private readonly PreschoolNotificationService $notificationService,
        private readonly PreschoolAutomationTaskService $automationTaskService,
        private readonly PreschoolWorkflowService $workflowService,
    ) {
    }

    public function dashboard(User $user, array $filters = []): array
    {
        $filters = $this->normalizeFilters($filters);
        $reportFilters = $this->reportFilters($filters);
        $analyticsFilters = $this->analyticsFilters($filters);
        $today = $this->resolveToday($filters);

        $reportDashboard = $this->reportingService->dashboard($user, $reportFilters);
        $attendance = $this->analyticsService->attendance($user, $analyticsFilters);
        $sessions = $this->analyticsService->sessions($user, $analyticsFilters);
        $alerts = $this->analyticsService->alerts($user, $analyticsFilters);
        $students = $this->analyticsService->students($user, $analyticsFilters);
        $teachers = $this->analyticsService->teachers($user, $analyticsFilters);
        $guardianContacts = $this->analyticsService->guardianContacts($user, $analyticsFilters);
        $health = $this->reportingService->section($user, 'health', $reportFilters);
        $payments = $this->reportingService->section($user, 'payments', $reportFilters);
        $assessments = $this->reportingService->section($user, 'assessments', $reportFilters);

        $sessionSummary = $this->attendanceSessionService->statusSummary($user, [
            'date' => $today,
            'start_date' => $analyticsFilters['date_from'] ?? null,
            'end_date' => $analyticsFilters['date_to'] ?? null,
        ]);

        $todaySessions = $this->attendanceSessionService->todaySessions($user, Carbon::parse($today));
        $missingSessions = $this->attendanceSessionService->missingSessions(
            $user,
            $analyticsFilters['date_from'] ?? null,
            $analyticsFilters['date_to'] ?? null,
        );

        $attendanceAlertSummary = $this->attendanceAlertService->summary($user, $reportFilters);
        $recentAlerts = $this->attendanceAlertService->recentAlerts($user, $reportFilters, 5);
        $recentCommunications = $this->guardianCommunicationService->listCommunications($user, array_merge($reportFilters, [
            'page' => 1,
            'per_page' => 5,
        ]));
        $notificationSummary = $this->notificationService->summary($user, $reportFilters);
        $recentNotifications = $this->notificationService->listNotifications($user, array_merge($reportFilters, [
            'page' => 1,
            'per_page' => 5,
        ]));
        $taskSummary = $this->automationTaskService->summary($user, $reportFilters);
        $recentTasks = $this->automationTaskService->listTasks($user, array_merge($reportFilters, [
            'page' => 1,
            'per_page' => 5,
        ]));
        $workflowBundle = $this->workflowService->listInstances($user, array_merge($reportFilters, [
            'page' => 1,
            'per_page' => 5,
        ]));
        $workflowSummary = $workflowBundle['summary'] ?? [];
        $workflowItems = $workflowBundle['items'] ?? [];

        $recentCommunicationsItems = $recentCommunications->getCollection() ?? collect();

        return [
            'scope' => 'operations',
            'summary' => $reportDashboard['summary'] ?? [],
            'today' => [
                'date' => $today,
                'attendanceRate' => $reportDashboard['summary']['attendanceRate'] ?? 0,
                'sessionSummary' => $sessionSummary,
                'openAlerts' => $attendanceAlertSummary['open'] ?? 0,
                'missingSessions' => $sessionSummary['missing'] ?? 0,
                'todaySessions' => $this->mapSessions($todaySessions->take(10)),
            ],
            'attendance' => [
                'summary' => $attendance['summary'] ?? [],
                'trends' => $attendance['trends'] ?? [],
                'breakdowns' => $attendance['breakdowns'] ?? [],
                'charts' => $attendance['charts'] ?? [],
                'datasets' => $attendance['datasets'] ?? [],
            ],
            'sessions' => [
                'summary' => array_merge($sessions['summary'] ?? [], $sessionSummary),
                'trends' => $sessions['trends'] ?? [],
                'breakdowns' => $sessions['breakdowns'] ?? [],
                'charts' => $sessions['charts'] ?? [],
                'datasets' => array_merge($sessions['datasets'] ?? [], [
                    'todaySessions' => $this->mapSessions($todaySessions->take(10)),
                    'missingSessions' => $this->mapSessions($missingSessions->take(10)),
                ]),
            ],
            'alerts' => [
                'summary' => array_merge($alerts['summary'] ?? [], $attendanceAlertSummary),
                'trends' => $alerts['trends'] ?? [],
                'breakdowns' => $alerts['breakdowns'] ?? [],
                'charts' => $alerts['charts'] ?? [],
                'datasets' => array_merge($alerts['datasets'] ?? [], [
                    'recentAlerts' => $recentAlerts,
                ]),
            ],
            'guardianCommunications' => [
                'summary' => $guardianContacts['summary'] ?? [],
                'trends' => $guardianContacts['trends'] ?? [],
                'breakdowns' => $guardianContacts['breakdowns'] ?? [],
                'charts' => $guardianContacts['charts'] ?? [],
                'datasets' => $guardianContacts['datasets'] ?? [],
                'items' => $this->mapCommunications($recentCommunicationsItems),
            ],
            'health' => [
                'summary' => $health['summary'] ?? [],
                'cards' => $health['cards'] ?? [],
                'trend' => $health['trend'] ?? [],
                'rows' => $health['rows'] ?? [],
                'alerts' => $health['alerts'] ?? [],
            ],
            'notifications' => [
                'summary' => $notificationSummary,
                'items' => $recentNotifications['items'] ?? [],
            ],
            'automationTasks' => [
                'summary' => $taskSummary,
                'items' => $recentTasks['items'] ?? [],
            ],
            'workflows' => [
                'summary' => [
                    'total' => $workflowSummary['total'] ?? 0,
                    'pendingWorkflows' => $workflowSummary['pendingWorkflows'] ?? 0,
                    'pendingApprovals' => $workflowSummary['pendingApprovals'] ?? ($workflowSummary['pendingApproval'] ?? 0),
                    'overdueWorkflows' => $workflowSummary['overdue'] ?? 0,
                    'escalatedWorkflows' => $workflowSummary['escalated'] ?? 0,
                    'recentlyUpdatedWorkflows' => $workflowSummary['recentlyUpdatedWorkflows'] ?? 0,
                    'myAssignments' => $workflowSummary['myAssignments'] ?? 0,
                ],
                'items' => $this->mapWorkflowItems(collect($workflowItems)),
                'recentActivity' => $this->mapWorkflowItems(collect($workflowItems)),
            ],
            'payments' => [
                'summary' => $payments['summary'] ?? [],
                'cards' => $payments['cards'] ?? [],
                'trend' => $payments['trend'] ?? [],
                'rows' => $payments['rows'] ?? [],
                'outstanding' => $payments['outstanding'] ?? [],
                'overdue' => $payments['overdue'] ?? [],
            ],
            'assessments' => [
                'summary' => $assessments['summary'] ?? [],
                'cards' => $assessments['cards'] ?? [],
                'trend' => $assessments['trend'] ?? [],
                'rows' => $assessments['rows'] ?? [],
                'table' => $assessments['table'] ?? [],
            ],
            'teachers' => [
                'summary' => $teachers['summary'] ?? [],
                'trends' => $teachers['trends'] ?? [],
                'breakdowns' => $teachers['breakdowns'] ?? [],
                'charts' => $teachers['charts'] ?? [],
                'datasets' => $teachers['datasets'] ?? [],
            ],
            'students' => [
                'summary' => $students['summary'] ?? [],
                'trends' => $students['trends'] ?? [],
                'breakdowns' => $students['breakdowns'] ?? [],
                'charts' => $students['charts'] ?? [],
                'datasets' => $students['datasets'] ?? [],
            ],
            'risks' => [
                'healthAlerts' => $reportDashboard['risk']['healthAlerts'] ?? 0,
                'guardianIssues' => $reportDashboard['risk']['guardianIssues'] ?? 0,
                'overdueInvoices' => $reportDashboard['risk']['overdueInvoices'] ?? 0,
                'missingSessions' => $sessionSummary['missing'] ?? 0,
                'openAttendanceAlerts' => $attendanceAlertSummary['open'] ?? 0,
                'unreadNotifications' => $notificationSummary['unread'] ?? 0,
                'openAutomationTasks' => $taskSummary['open'] ?? 0,
                'overdueAutomationTasks' => $taskSummary['overdue'] ?? 0,
                'criticalNotifications' => $notificationSummary['critical'] ?? 0,
                'pendingWorkflows' => $workflowSummary['pendingWorkflows'] ?? 0,
                'pendingWorkflowApprovals' => $workflowSummary['pendingApprovals'] ?? ($workflowSummary['pendingApproval'] ?? 0),
                'overdueWorkflows' => $workflowSummary['overdue'] ?? 0,
                'escalatedWorkflows' => $workflowSummary['escalated'] ?? 0,
            ],
            'timeline' => $this->buildTimeline(
                $reportDashboard['recentAttendance'] ?? [],
                $recentAlerts,
                $this->mapCommunications($recentCommunicationsItems),
                $recentNotifications['items'] ?? [],
                $recentTasks['items'] ?? [],
                $this->mapWorkflowItems(collect($workflowItems)),
                $this->mapSessions($todaySessions->take(5)),
            ),
            'quickActions' => $this->quickActions(),
            'generatedAt' => now()->toISOString(),
        ];
    }

    private function normalizeFilters(array $filters): array
    {
        return [
            'academic_year_id' => $this->nullableInt($filters['academic_year_id'] ?? $filters['academicYearId'] ?? null),
            'class_id' => $this->nullableInt($filters['class_id'] ?? $filters['classId'] ?? null),
            'teacher_user_id' => $this->nullableInt($filters['teacher_user_id'] ?? $filters['teacherUserId'] ?? null),
            'date_from' => $this->nullableDate($filters['date_from'] ?? $filters['dateFrom'] ?? null),
            'date_to' => $this->nullableDate($filters['date_to'] ?? $filters['dateTo'] ?? null),
            'status' => $this->nullableString($filters['status'] ?? null),
        ];
    }

    private function analyticsFilters(array $filters): array
    {
        return array_filter($this->normalizeFilters($filters), static fn ($value) => $value !== null && $value !== '');
    }

    private function reportFilters(array $filters): array
    {
        return array_filter([
            'academicYearId' => $filters['academic_year_id'] ?? null,
            'classId' => $filters['class_id'] ?? null,
            'teacherUserId' => $filters['teacher_user_id'] ?? null,
            'dateFrom' => $filters['date_from'] ?? null,
            'dateTo' => $filters['date_to'] ?? null,
            'status' => $filters['status'] ?? null,
        ], static fn ($value) => $value !== null && $value !== '');
    }

    private function resolveToday(array $filters): string
    {
        return $filters['date_from']
            ?? $filters['date_to']
            ?? now()->toDateString();
    }

    private function mapSessions(Collection $sessions): array
    {
        return $sessions->map(static function ($session): array {
            return [
                'id' => $session->id,
                'classId' => $session->preschool_class_id ?? $session->class_id ?? null,
                'className' => $session->className ?? $session->preschoolClass?->name ?? null,
                'scheduleLabel' => $session->scheduleLabel ?? $session->schedule?->title ?? null,
                'status' => $session->status ?? null,
                'attendanceDate' => $session->attendance_date?->toDateString() ?? $session->attendanceDate ?? null,
                'startTime' => $session->start_time ?? null,
                'endTime' => $session->end_time ?? null,
                'teacherName' => $session->teacherName ?? $session->preschoolClass?->teacher?->name ?? null,
                'recordCount' => $session->attendanceRecordsCount ?? $session->attendance_records_count ?? null,
            ];
        })->values()->all();
    }

    private function mapCommunications(Collection $communications): array
    {
        return $communications->map(static function ($communication): array {
            return [
                'id' => $communication->id,
                'studentId' => $communication->student_id,
                'guardianId' => $communication->guardian_id,
                'status' => $communication->status,
                'channel' => $communication->channel,
                'severity' => $communication->severity,
                'subject' => $communication->subject,
                'message' => $communication->message,
                'createdAt' => $communication->created_at?->toISOString(),
                'acknowledgedAt' => $communication->acknowledged_at?->toISOString(),
            ];
        })->values()->all();
    }

    private function mapWorkflowItems(Collection $workflows): array
    {
        return $workflows->map(static function (array $workflow): array {
            return [
                'id' => $workflow['id'] ?? null,
                'workflowDefinitionName' => $workflow['workflowDefinitionName'] ?? null,
                'workflowDefinitionKey' => $workflow['workflowDefinitionKey'] ?? null,
                'sourceLabel' => $workflow['sourceLabel'] ?? null,
                'sourceType' => $workflow['sourceType'] ?? null,
                'sourceId' => $workflow['sourceId'] ?? null,
                'sourceRouteName' => $workflow['sourceRouteName'] ?? null,
                'sourceRouteParams' => $workflow['sourceRouteParams'] ?? [],
                'sourceExists' => $workflow['sourceExists'] ?? false,
                'status' => $workflow['status'] ?? null,
                'priority' => $workflow['priority'] ?? null,
                'currentStep' => $workflow['currentStep'] ?? null,
                'assignedRole' => $workflow['assignedRole'] ?? null,
                'assignee' => $workflow['assignee'] ?? null,
                'dueAt' => $workflow['dueAt'] ?? null,
                'updatedAt' => $workflow['updatedAt'] ?? null,
            ];
        })->values()->all();
    }

    private function buildTimeline(array $recentAttendance, array $recentAlerts, array $recentCommunications, array $recentNotifications, array $recentTasks, array $recentWorkflows, array $recentSessions): array
    {
        $timeline = [];

        foreach ($recentAttendance as $item) {
            $timeline[] = [
                'type' => 'attendance',
                'label' => $item['studentName'] ?? 'Attendance',
                'text' => $item['className'] ?? '',
                'status' => $item['status'] ?? null,
                'createdAt' => $item['attendanceDate'] ?? null,
            ];
        }

        foreach ($recentAlerts as $item) {
            $timeline[] = [
                'type' => 'alert',
                'label' => $item['alertLabel'] ?? 'Alert',
                'text' => $item['studentName'] ?? '',
                'status' => $item['followUpStatus'] ?? $item['status'] ?? null,
                'createdAt' => $item['createdAt'] ?? null,
            ];
        }

        foreach ($recentCommunications as $item) {
            $timeline[] = [
                'type' => 'guardian_communication',
                'label' => $item['subject'] ?? 'Communication',
                'text' => $item['message'] ?? '',
                'status' => $item['status'] ?? null,
                'createdAt' => $item['createdAt'] ?? null,
            ];
        }

        foreach ($recentNotifications as $item) {
            $timeline[] = [
                'type' => 'notification',
                'label' => $item['title'] ?? 'Notification',
                'text' => $item['body'] ?? '',
                'status' => $item['status'] ?? null,
                'createdAt' => $item['createdAt'] ?? null,
            ];
        }

        foreach ($recentTasks as $item) {
            $timeline[] = [
                'type' => 'automation_task',
                'label' => $item['title'] ?? 'Task',
                'text' => $item['description'] ?? '',
                'status' => $item['status'] ?? null,
                'createdAt' => $item['createdAt'] ?? null,
            ];
        }

        foreach ($recentWorkflows as $item) {
            $timeline[] = [
                'type' => 'workflow',
                'label' => $item['sourceLabel'] ?? $item['workflowDefinitionName'] ?? 'Workflow',
                'text' => trim(($item['status'] ?? '')) ?: ($item['currentStep']['name'] ?? $item['currentStep']['key'] ?? ''),
                'status' => $item['status'] ?? null,
                'createdAt' => $item['updatedAt'] ?? null,
            ];
        }

        foreach ($recentSessions as $item) {
            $timeline[] = [
                'type' => 'session',
                'label' => $item['className'] ?? 'Session',
                'text' => $item['scheduleLabel'] ?? '',
                'status' => $item['status'] ?? null,
                'createdAt' => $item['attendanceDate'] ?? null,
            ];
        }

        return collect($timeline)
            ->filter(static fn (array $item): bool => trim((string) ($item['createdAt'] ?? '')) !== '')
            ->sortByDesc('createdAt')
            ->values()
            ->all();
    }

    private function quickActions(): array
    {
        return [
            ['label' => 'Take Attendance', 'routeName' => 'dashboard-preschool-admin-attendance'],
            ['label' => 'View Sessions', 'routeName' => 'dashboard-preschool-admin-attendance-history'],
            ['label' => 'Open Attendance Alerts', 'routeName' => 'dashboard-preschool-admin-attendance-alerts'],
            ['label' => 'Open Student Profile', 'routeName' => 'dashboard-preschool-admin-students'],
            ['label' => 'Open Guardian Communications', 'routeName' => 'dashboard-preschool-admin-guardian-communications'],
            ['label' => 'View Health Records', 'routeName' => 'dashboard-preschool-admin-health'],
            ['label' => 'Review Assessments', 'routeName' => 'dashboard-preschool-admin-reports-assessments'],
            ['label' => 'Open Invoices', 'routeName' => 'dashboard-preschool-admin-payment'],
            ['label' => 'Generate Report', 'routeName' => 'dashboard-preschool-admin-reports'],
        ];
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

        return $value === '' ? null : $value;
    }
}



