<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PreschoolReportingService
{
    public function __construct(
        private readonly PreschoolAttendanceConfigurationService $attendanceConfigurationService,
        private readonly PreschoolHealthConfigurationService $healthConfigurationService,
        private readonly PreschoolPaymentConfigurationService $paymentConfigurationService,
        private readonly PreschoolPreferencesService $preferencesService,
    ) {
    }

    public function getOperationsDashboard(array $filters = []): array
    {
        $attendance = $this->getAttendanceSummary($filters);
        $assessments = $this->getAssessmentSummary($filters);
        $health = $this->getHealthSummary($filters);
        $payments = $this->getPaymentSummary($filters);
        $enrollments = $this->getEnrollmentSummary($filters);
        $guardians = $this->getGuardianSummary($filters);

        $risk = $this->getRiskSummary($attendance, $assessments, $health, $payments, $guardians);

        return [
            'report' => 'dashboard',
            'filters' => $this->normalizeFilters($filters),
            'kpis' => [
                'total_students' => $enrollments['summary']['total_students'],
                'active_students' => $enrollments['summary']['active_students'],
                'new_enrollments' => $enrollments['summary']['new_enrollments'],
                'attendance_rate' => $attendance['summary']['attendance_rate'],
                'absence_rate' => $attendance['summary']['absence_rate'],
                'late_rate' => $attendance['summary']['late_rate'],
                'assessment_completion' => $assessments['summary']['completion_rate'],
                'average_score' => $assessments['summary']['average_score'],
                'at_risk_students' => $risk['total_at_risk_students'],
                'open_health_alerts' => $health['summary']['open_alerts'],
                'critical_health_alerts' => $health['summary']['critical_alerts'],
                'vaccination_compliance' => $health['summary']['vaccination_compliance'],
                'revenue' => $payments['summary']['revenue'],
                'outstanding_balances' => $payments['summary']['outstanding_balances'],
                'overdue_invoices' => $payments['summary']['overdue_invoices'],
                'open_guardian_issues' => $guardians['summary']['open_issues'],
                'escalated_cases' => $guardians['summary']['escalated_cases'],
            ],
            'cards' => [
                $this->makeCard('Students', $enrollments['summary']['active_students'], 'Active student records', 'info'),
                $this->makeCard('Attendance', $attendance['summary']['attendance_rate'].'%', 'Attendance rate', 'success'),
                $this->makeCard('Assessment', $assessments['summary']['completion_rate'].'%', 'Completion rate', 'warning'),
                $this->makeCard('Health', $health['summary']['open_alerts'], 'Open alerts', 'error'),
                $this->makeCard('Payments', $payments['summary']['revenue'], 'Revenue', 'info'),
                $this->makeCard('Governance', $guardians['summary']['open_issues'], 'Open guardian issues', 'warning'),
            ],
            'modules' => [
                'attendance' => $attendance['summary'],
                'assessments' => $assessments['summary'],
                'health' => $health['summary'],
                'payments' => $payments['summary'],
                'enrollments' => $enrollments['summary'],
                'guardians' => $guardians['summary'],
            ],
            'risk' => $risk,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    public function getAttendanceSummary(array $filters = []): array
    {
        $settings = $this->attendanceConfigurationService->getSettings();
        $calendarEventsCount = $settings['calendar_events_count'] ?? 0;
        $schoolDays = $settings['school_days_per_week'] ?? 0;

        return [
            'report' => 'attendance',
            'filters' => $this->normalizeFilters($filters),
            'summary' => [
                'total_students' => $this->tableCount('preschool_students'),
                'active_students' => $this->tableCount('preschool_students', ['status' => 'active']),
                'attendance_rate' => $this->buildRate($this->tableCount('preschool_attendance_records', ['status' => 'present']), $this->tableCount('preschool_attendance_records')),
                'absence_rate' => $this->buildRate($this->tableCount('preschool_attendance_records', ['status' => 'absent']), $this->tableCount('preschool_attendance_records')),
                'late_rate' => $this->buildRate($this->tableCount('preschool_attendance_records', ['status' => 'late']), $this->tableCount('preschool_attendance_records')),
                'present_count' => $this->tableCount('preschool_attendance_records', ['status' => 'present']),
                'late_count' => $this->tableCount('preschool_attendance_records', ['status' => 'late']),
                'absent_count' => $this->tableCount('preschool_attendance_records', ['status' => 'absent']),
                'school_days_per_week' => $schoolDays,
                'calendar_events_count' => $calendarEventsCount,
                'late_threshold_minutes' => $settings['late_threshold_minutes'],
                'absence_alert_days' => $settings['absence_alert_days'],
                'at_risk_students' => $this->tableCount('preschool_students', ['risk_status' => 'attendance']),
            ],
            'cards' => [
                $this->makeCard('Attendance rate', $this->buildRate($this->tableCount('preschool_attendance_records', ['status' => 'present']), $this->tableCount('preschool_attendance_records')).'%', 'Present vs total records', 'success'),
                $this->makeCard('Late rate', $this->buildRate($this->tableCount('preschool_attendance_records', ['status' => 'late']), $this->tableCount('preschool_attendance_records')).'%', 'Late arrivals', 'warning'),
                $this->makeCard('Absent rate', $this->buildRate($this->tableCount('preschool_attendance_records', ['status' => 'absent']), $this->tableCount('preschool_attendance_records')).'%', 'Absences', 'error'),
                $this->makeCard('Calendar events', $calendarEventsCount, 'School calendar entries', 'info'),
            ],
            'trend' => $this->buildTrendSeries('attendance'),
            'class_breakdown' => $this->buildClassBreakdown('attendance'),
            'student_breakdown' => $this->buildStudentBreakdown('attendance'),
            'export_formats' => ['pdf', 'excel', 'csv'],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    public function getAttendanceByClass(array $filters = []): array
    {
        $payload = $this->getAttendanceSummary($filters);
        return [
            ...$payload,
            'section' => 'attendance_class',
            'rows' => $payload['class_breakdown'],
        ];
    }

    public function getAttendanceByStudent(array $filters = []): array
    {
        $payload = $this->getAttendanceSummary($filters);
        return [
            ...$payload,
            'section' => 'attendance_student',
            'rows' => $payload['student_breakdown'],
        ];
    }

    public function getAttendanceTrend(array $filters = []): array
    {
        return [
            'report' => 'attendance_trend',
            'filters' => $this->normalizeFilters($filters),
            'trend' => $this->buildTrendSeries('attendance'),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    public function getAssessmentSummary(array $filters = []): array
    {
        $settings = $this->preferencesService->getPreferences();
        $passingScore = $settings['passing_score'] ?? 60;
        $categories = $this->countRowsWithTableGuard('preschool_assessment_categories');
        $reportPeriods = $this->countRowsWithTableGuard('preschool_assessment_report_periods');

        return [
            'report' => 'assessments',
            'filters' => $this->normalizeFilters($filters),
            'summary' => [
                'completion_rate' => $this->buildRate($this->tableCount('preschool_assessments', ['status' => 'completed']), $this->tableCount('preschool_assessments')),
                'average_score' => $this->tableAverage('preschool_assessments', 'score'),
                'passing_score' => $passingScore,
                'at_risk_students' => $this->tableCount('preschool_students', ['risk_status' => 'assessment']),
                'grade_bands' => $this->tableCount('preschool_assessment_grading_scales'),
                'categories_count' => $categories,
                'report_periods_count' => $reportPeriods,
            ],
            'performance' => $this->buildPerformanceSeries('assessment'),
            'completion' => $this->buildCompletionSeries('assessment'),
            'trend' => $this->buildTrendSeries('assessment'),
            'table' => $this->buildAssessmentRows(),
            'export_formats' => ['pdf', 'excel', 'csv'],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    public function getAssessmentPerformance(array $filters = []): array
    {
        $payload = $this->getAssessmentSummary($filters);
        return [
            ...$payload,
            'section' => 'assessment_performance',
        ];
    }

    public function getAssessmentCompletionRates(array $filters = []): array
    {
        $payload = $this->getAssessmentSummary($filters);
        return [
            ...$payload,
            'section' => 'assessment_completion',
        ];
    }

    public function getAssessmentTrend(array $filters = []): array
    {
        return [
            'report' => 'assessment_trend',
            'filters' => $this->normalizeFilters($filters),
            'trend' => $this->buildTrendSeries('assessment'),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    public function getHealthSummary(array $filters = []): array
    {
        $settings = $this->healthConfigurationService->getSettings();

        return [
            'report' => 'health',
            'filters' => $this->normalizeFilters($filters),
            'summary' => [
                'open_alerts' => $this->tableCount('preschool_health_alerts', ['status' => 'open']),
                'critical_alerts' => $this->tableCount('preschool_health_alerts', ['severity_code' => 'critical']),
                'vaccination_compliance' => $this->buildComplianceRate('preschool_vaccination_records', 'status', 'complete'),
                'severity_levels_count' => $this->tableCount('preschool_health_severity_levels'),
                'incident_categories_count' => $this->tableCount('preschool_health_incident_categories'),
                'vaccination_categories_count' => $this->tableCount('preschool_vaccination_categories'),
                'health_check_categories_count' => $this->tableCount('preschool_health_check_categories'),
                'medication_reminder_enabled' => $settings['medication_reminder_enabled'],
                'vaccination_reminder_enabled' => $settings['vaccination_reminder_enabled'],
                'at_risk_students' => $this->tableCount('preschool_health_alerts', ['requires_acknowledgment' => 1]),
            ],
            'incidents' => $this->buildHealthIncidentRows(),
            'alerts' => $this->buildHealthAlertRows(),
            'vaccinations' => $this->buildVaccinationRows(),
            'export_formats' => ['pdf', 'excel', 'csv'],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    public function getHealthIncidents(array $filters = []): array
    {
        $payload = $this->getHealthSummary($filters);
        return [
            ...$payload,
            'section' => 'health_incidents',
            'rows' => $payload['incidents'],
        ];
    }

    public function getHealthAlerts(array $filters = []): array
    {
        $payload = $this->getHealthSummary($filters);
        return [
            ...$payload,
            'section' => 'health_alerts',
            'rows' => $payload['alerts'],
        ];
    }

    public function getVaccinationCompliance(array $filters = []): array
    {
        $payload = $this->getHealthSummary($filters);
        return [
            ...$payload,
            'section' => 'vaccination_compliance',
            'rows' => $payload['vaccinations'],
        ];
    }

    public function getPaymentSummary(array $filters = []): array
    {
        $settings = $this->paymentConfigurationService->getSettings();

        return [
            'report' => 'payments',
            'filters' => $this->normalizeFilters($filters),
            'summary' => [
                'fee_types_count' => $this->tableCount('preschool_fee_types'),
                'payment_methods_count' => $this->tableCount('preschool_payment_methods'),
                'late_fee_enabled' => $settings['late_fee_enabled'],
                'grace_period_days' => $settings['grace_period_days'],
                'invoice_prefix' => $settings['invoice_prefix'],
                'receipt_prefix' => $settings['receipt_prefix'],
                'late_fee_type' => $settings['late_fee_type'],
                'late_fee_amount' => $settings['late_fee_amount'],
                'proration_enabled' => $settings['proration_enabled'],
                'revenue' => $this->tableSum('preschool_payments', 'amount'),
                'outstanding_balances' => $this->tableSum('preschool_invoices', 'balance_due'),
                'overdue_invoices' => $this->tableCount('preschool_invoices', ['status' => 'overdue']),
                'at_risk_students' => $this->tableCount('preschool_invoices', ['status' => 'overdue']),
            ],
            'revenue' => $this->buildRevenueSeries(),
            'outstanding' => $this->buildOutstandingSeries(),
            'overdue' => $this->buildOverdueInvoices(),
            'export_formats' => ['pdf', 'excel', 'csv'],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    public function getRevenueSummary(array $filters = []): array
    {
        $payload = $this->getPaymentSummary($filters);
        return [
            ...$payload,
            'section' => 'payments_revenue',
            'rows' => $payload['revenue'],
        ];
    }

    public function getOutstandingBalances(array $filters = []): array
    {
        $payload = $this->getPaymentSummary($filters);
        return [
            ...$payload,
            'section' => 'payments_outstanding',
            'rows' => $payload['outstanding'],
        ];
    }

    public function getOverdueInvoices(array $filters = []): array
    {
        $payload = $this->getPaymentSummary($filters);
        return [
            ...$payload,
            'section' => 'payments_overdue',
            'rows' => $payload['overdue'],
        ];
    }

    public function getEnrollmentSummary(array $filters = []): array
    {
        $preferences = $this->preferencesService->getPreferences();

        return [
            'report' => 'enrollments',
            'filters' => $this->normalizeFilters($filters),
            'summary' => [
                'total_students' => $this->tableCount('preschool_students'),
                'active_students' => $this->tableCount('preschool_students', ['status' => 'active']),
                'new_enrollments' => $this->tableCount('preschool_enrollments', ['status' => 'new']),
                'admissions' => $this->tableCount('preschool_enrollments', ['status' => 'admitted']),
                'waitlist_count' => $this->tableCount('preschool_enrollments', ['status' => 'waitlisted']),
                'capacity_utilization' => $preferences['default_class_capacity'] ? $this->buildRate($this->tableCount('preschool_students'), max($this->tableCount('preschool_classes') * (int) $preferences['default_class_capacity'], 1)) : 0,
            ],
            'trend' => $this->buildTrendSeries('enrollment'),
            'admissions' => $this->buildAdmissionsReport(),
            'export_formats' => ['pdf', 'excel', 'csv'],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    public function getEnrollmentTrend(array $filters = []): array
    {
        $payload = $this->getEnrollmentSummary($filters);
        return [
            ...$payload,
            'section' => 'enrollment_trend',
            'rows' => $payload['trend'],
        ];
    }

    public function getAdmissionsReport(array $filters = []): array
    {
        $payload = $this->getEnrollmentSummary($filters);
        return [
            ...$payload,
            'section' => 'admissions',
            'rows' => $payload['admissions'],
        ];
    }

    public function getGuardianSummary(array $filters = []): array
    {
        return [
            'report' => 'guardians',
            'filters' => $this->normalizeFilters($filters),
            'summary' => [
                'total_guardians' => $this->tableCount('preschool_guardians'),
                'open_issues' => $this->tableCount('preschool_guardian_issues', ['status' => 'open']),
                'escalated_cases' => $this->tableCount('preschool_guardian_issues', ['status' => 'escalated']),
                'communications_sent' => $this->tableCount('preschool_guardian_communications'),
                'at_risk_students' => $this->tableCount('preschool_guardian_issues', ['severity' => 'high']),
            ],
            'issues' => $this->buildGuardianIssueRows(),
            'communications' => $this->buildGuardianCommunicationRows(),
            'export_formats' => ['pdf', 'excel', 'csv'],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    public function getGuardianIssueReport(array $filters = []): array
    {
        $payload = $this->getGuardianSummary($filters);
        return [
            ...$payload,
            'section' => 'guardian_issues',
            'rows' => $payload['issues'],
        ];
    }

    public function getGuardianCommunicationReport(array $filters = []): array
    {
        $payload = $this->getGuardianSummary($filters);
        return [
            ...$payload,
            'section' => 'guardian_communications',
            'rows' => $payload['communications'],
        ];
    }

    public function exportReport(string $section, string $format, array $filters = []): array
    {
        $format = strtolower(trim($format));
        $section = strtolower(trim($section));
        $payload = $this->resolveReportPayload($section, $filters);
        $rows = $this->extractRowsForExport($payload);
        $headers = $this->extractHeadersForExport($rows);
        $content = $this->buildDelimitedContent($headers, $rows, $format === 'csv' ? ',' : "\t");

        return [
            'section' => $section,
            'format' => $format,
            'filename' => sprintf('preschool-%s-report-%s.%s', $section, Carbon::now()->format('Ymd-His'), $format === 'pdf' ? 'pdf' : ($format === 'excel' ? 'xlsx' : 'csv')),
            'mime_type' => match ($format) {
                'pdf' => 'application/pdf',
                'excel' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                default => 'text/csv;charset=utf-8',
            },
            'content' => $content,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    public function getReportFilters(): array
    {
        return [
            'academic_years' => $this->tableOptions('preschool_academic_years', 'label'),
            'terms' => $this->tableOptions('preschool_terms', 'name'),
            'classes' => $this->tableOptions('preschool_classes', 'name'),
            'teachers' => $this->tableOptions('users', 'name', ['role_code' => 'teacher-preschool']),
            'statuses' => [
                ['label' => 'Active', 'value' => 'active'],
                ['label' => 'Archived', 'value' => 'archived'],
                ['label' => 'Open', 'value' => 'open'],
                ['label' => 'Closed', 'value' => 'closed'],
            ],
            'export_formats' => [
                ['label' => 'PDF', 'value' => 'pdf'],
                ['label' => 'Excel', 'value' => 'excel'],
                ['label' => 'CSV', 'value' => 'csv'],
            ],
        ];
    }

    public function getRiskSummary(array $attendance, array $assessments, array $health, array $payments, array $guardians): array
    {
        return [
            'total_at_risk_students' => (int) (
                ($attendance['summary']['at_risk_students'] ?? 0)
                + ($assessments['summary']['at_risk_students'] ?? 0)
                + ($health['summary']['at_risk_students'] ?? 0)
                + ($payments['summary']['at_risk_students'] ?? 0)
                + ($guardians['summary']['at_risk_students'] ?? 0)
            ),
            'attendance' => $attendance['summary']['at_risk_students'] ?? 0,
            'assessment' => $assessments['summary']['at_risk_students'] ?? 0,
            'health' => $health['summary']['at_risk_students'] ?? 0,
            'payments' => $payments['summary']['at_risk_students'] ?? 0,
            'guardians' => $guardians['summary']['at_risk_students'] ?? 0,
        ];
    }

    protected function resolveReportPayload(string $section, array $filters = []): array
    {
        return match ($section) {
            'dashboard' => $this->getOperationsDashboard($filters),
            'attendance', 'attendance_overview' => $this->getAttendanceSummary($filters),
            'attendance_class' => $this->getAttendanceByClass($filters),
            'attendance_student' => $this->getAttendanceByStudent($filters),
            'attendance_trend' => $this->getAttendanceTrend($filters),
            'assessments' => $this->getAssessmentSummary($filters),
            'assessment_performance' => $this->getAssessmentPerformance($filters),
            'assessment_completion' => $this->getAssessmentCompletionRates($filters),
            'assessment_trend' => $this->getAssessmentTrend($filters),
            'health' => $this->getHealthSummary($filters),
            'health_incidents' => $this->getHealthIncidents($filters),
            'health_alerts' => $this->getHealthAlerts($filters),
            'vaccination_compliance' => $this->getVaccinationCompliance($filters),
            'payments' => $this->getPaymentSummary($filters),
            'payments_revenue' => $this->getRevenueSummary($filters),
            'payments_outstanding' => $this->getOutstandingBalances($filters),
            'payments_overdue' => $this->getOverdueInvoices($filters),
            'enrollments' => $this->getEnrollmentSummary($filters),
            'enrollment_trend' => $this->getEnrollmentTrend($filters),
            'admissions' => $this->getAdmissionsReport($filters),
            'guardians' => $this->getGuardianSummary($filters),
            'guardian_issues' => $this->getGuardianIssueReport($filters),
            'guardian_communications' => $this->getGuardianCommunicationReport($filters),
            default => $this->getOperationsDashboard($filters),
        };
    }

    protected function normalizeFilters(array $filters): array
    {
        return [
            'academic_year_id' => $filters['academic_year_id'] ?? '',
            'term_id' => $filters['term_id'] ?? '',
            'date_from' => $filters['date_from'] ?? '',
            'date_to' => $filters['date_to'] ?? '',
            'month' => $filters['month'] ?? '',
            'quarter' => $filters['quarter'] ?? '',
            'class_id' => $filters['class_id'] ?? '',
            'teacher_id' => $filters['teacher_id'] ?? '',
            'status' => $filters['status'] ?? '',
            'export_format' => $filters['export_format'] ?? '',
        ];
    }

    protected function makeCard(string $title, mixed $value, string $caption, string $tone): array
    {
        return compact('title', 'value', 'caption', 'tone');
    }

    protected function buildTrendSeries(string $type): array
    {
        return [
            ['label' => 'Jan', 'value' => 0],
            ['label' => 'Feb', 'value' => 0],
            ['label' => 'Mar', 'value' => 0],
            ['label' => 'Apr', 'value' => 0],
            ['label' => 'May', 'value' => 0],
            ['label' => 'Jun', 'value' => 0],
        ];
    }

    protected function buildPerformanceSeries(string $type): array
    {
        return [
            ['label' => 'Quiz', 'value' => 0],
            ['label' => 'Assignment', 'value' => 0],
            ['label' => 'Observation', 'value' => 0],
        ];
    }

    protected function buildCompletionSeries(string $type): array
    {
        return [
            ['label' => 'Completed', 'value' => 0],
            ['label' => 'Pending', 'value' => 0],
            ['label' => 'At risk', 'value' => 0],
        ];
    }

    protected function buildClassBreakdown(string $type): array
    {
        return [];
    }

    protected function buildStudentBreakdown(string $type): array
    {
        return [];
    }

    protected function buildAssessmentRows(): array
    {
        return [];
    }

    protected function buildHealthIncidentRows(): array
    {
        return [];
    }

    protected function buildHealthAlertRows(): array
    {
        return [];
    }

    protected function buildVaccinationRows(): array
    {
        return [];
    }

    protected function buildRevenueSeries(): array
    {
        return [
            ['label' => 'Jan', 'value' => 0],
            ['label' => 'Feb', 'value' => 0],
            ['label' => 'Mar', 'value' => 0],
        ];
    }

    protected function buildOutstandingSeries(): array
    {
        return [];
    }

    protected function buildOverdueInvoices(): array
    {
        return [];
    }

    protected function buildAdmissionsReport(): array
    {
        return [];
    }

    protected function buildGuardianIssueRows(): array
    {
        return [];
    }

    protected function buildGuardianCommunicationRows(): array
    {
        return [];
    }

    protected function extractRowsForExport(array $payload): array
    {
        foreach (['rows', 'cards', 'trend', 'performance', 'completion', 'issues', 'communications', 'incidents', 'alerts', 'vaccinations', 'outstanding', 'overdue', 'admissions', 'class_breakdown', 'student_breakdown'] as $key) {
            if (! empty($payload[$key]) && is_array($payload[$key])) {
                return $payload[$key];
            }
        }

        if (! empty($payload['modules']) && is_array($payload['modules'])) {
            return array_map(
                fn (string $key, mixed $value): array => ['label' => $key, 'value' => $value],
                array_keys($payload['modules']),
                array_values($payload['modules']),
            );
        }

        if (! empty($payload['kpis']) && is_array($payload['kpis'])) {
            return array_map(
                fn (string $key, mixed $value): array => ['label' => $key, 'value' => $value],
                array_keys($payload['kpis']),
                array_values($payload['kpis']),
            );
        }

        return [];
    }

    protected function extractHeadersForExport(array $rows): array
    {
        $first = $rows[0] ?? [];

        if (! is_array($first)) {
            return ['label', 'value'];
        }

        return array_keys($first);
    }

    protected function buildDelimitedContent(array $headers, array $rows, string $delimiter): string
    {
        $lines = [];
        $lines[] = implode($delimiter, $headers);

        foreach ($rows as $row) {
            $values = [];
            foreach ($headers as $header) {
                $values[] = (string) data_get($row, $header, '');
            }
            $lines[] = implode($delimiter, $values);
        }

        return implode("\n", $lines);
    }

    protected function countRowsWithTableGuard(string $table): int
    {
        return $this->tableCount($table);
    }

    protected function tableCount(string $table, array $where = []): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table);

        foreach ($where as $column => $value) {
            $query->where($column, $value);
        }

        return (int) $query->count();
    }

    protected function tableSum(string $table, string $column): int|float
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return DB::table($table)->sum($column);
    }

    protected function tableAverage(string $table, string $column): int|float|null
    {
        if (! Schema::hasTable($table)) {
            return null;
        }

        $value = DB::table($table)->avg($column);
        return $value !== null ? round((float) $value, 2) : null;
    }

    protected function tableOptions(string $table, string $labelColumn, array $where = []): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $query = DB::table($table);

        foreach ($where as $column => $value) {
            $query->where($column, $value);
        }

        return $query->limit(25)->get()->map(fn ($row): array => [
            'label' => (string) ($row->{$labelColumn} ?? ''),
            'value' => (string) ($row->id ?? ''),
        ])->all();
    }

    protected function buildRate(int|float $numerator, int|float $denominator): float
    {
        if ((float) $denominator <= 0) {
            return 0.0;
        }

        return round(((float) $numerator / (float) $denominator) * 100, 2);
    }

    protected function buildComplianceRate(string $table, string $column, mixed $acceptedValue): float
    {
        $total = $this->tableCount($table);
        if ($total <= 0) {
            return 0.0;
        }

        return $this->buildRate($this->tableCount($table, [$column => $acceptedValue]), $total);
    }
}
