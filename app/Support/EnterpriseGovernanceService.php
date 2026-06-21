<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\SecurityEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EnterpriseGovernanceService
{
    public function __construct(
        private readonly PreschoolAttendanceConfigurationService $attendanceConfigurationService,
        private readonly PreschoolHealthConfigurationService $healthConfigurationService,
        private readonly PreschoolPaymentConfigurationService $paymentConfigurationService,
        private readonly PreschoolPreferencesService $preferencesService,
    ) {
    }

    public function getDashboard(array $filters = []): array
    {
        $audit = $this->getAuditSummary($filters);
        $security = $this->getSecuritySummary($filters);
        $risk = $this->getRiskDashboard($filters);
        $configuration = $this->getConfigurationSummary($filters);

        return [
            'dashboard' => [
                'security' => $security,
                'audit' => $audit,
                'risk' => $risk,
                'configuration' => $configuration,
                'generated_at' => now()->toIso8601String(),
            ],
        ];
    }

    public function getAuditSummary(array $filters = []): array
    {
        $today = Carbon::today()->toDateString();
        $monthStart = Carbon::now()->startOfMonth()->toDateString();

        return [
            'failed_logins_today' => $this->countAuditLogs(['event_type' => 'LOGIN_FAILED'], $today, $today),
            'password_resets' => $this->countAuditLogs(['event_type' => 'PASSWORD_RESET']),
            'active_security_events' => $this->countSecurityEvents(['resolved_at' => null]),
            'critical_events' => $this->countSecurityEvents(['severity' => 'critical', 'resolved_at' => null]),
            'audit_events_today' => $this->countAuditLogs([], $today, $today),
            'audit_events_this_month' => $this->countAuditLogs([], $monthStart, $today),
            'top_modules' => $this->topModules(),
        ];
    }

    public function getSecuritySummary(array $filters = []): array
    {
        return [
            'failed_logins_today' => $this->countSecurityEvents(['event_type' => 'failed_login']),
            'password_resets' => $this->countSecurityEvents(['event_type' => 'password_reset']),
            'active_security_events' => $this->countSecurityEvents(['resolved_at' => null]),
            'critical_events' => $this->countSecurityEvents(['severity' => 'critical', 'resolved_at' => null]),
        ];
    }

    public function getConfigurationSummary(array $filters = []): array
    {
        $today = Carbon::today()->toDateString();
        $monthStart = Carbon::now()->startOfMonth()->toDateString();

        return [
            'changes_today' => $this->countAuditLogs(['event_type' => 'SETTINGS_UPDATED'], $today, $today),
            'changes_this_month' => $this->countAuditLogs(['event_type' => 'SETTINGS_UPDATED'], $monthStart, $today),
            'last_configuration_update' => $this->latestConfigurationChange(),
        ];
    }

    public function getRiskDashboard(array $filters = []): array
    {
        $health = $this->healthConfigurationService->getDashboardSummary();
        $payments = $this->paymentConfigurationService->getDashboardSummary();

        return [
            'at_risk_students' => $this->getAtRiskStudents($filters),
            'overdue_payments' => (int) ($payments['overdue_invoices'] ?? 0),
            'open_health_alerts' => (int) ($health['severity_levels_count'] ?? 0),
            'open_guardian_issues' => 0,
        ];
    }

    public function calculateStudentRisk(array $student = []): array
    {
        $score = 0;
        $factors = [];

        if (($student['attendance_rate'] ?? 100) < 75) {
            $score += 30;
            $factors[] = 'excessive_absence';
        }

        if (($student['late_count'] ?? 0) > 5) {
            $score += 20;
            $factors[] = 'excessive_lateness';
        }

        if (($student['average_score'] ?? 100) < 60) {
            $score += 25;
            $factors[] = 'below_passing_score';
        }

        if (($student['critical_health_alerts'] ?? 0) > 0) {
            $score += 25;
            $factors[] = 'unresolved_critical_alert';
        }

        if (($student['overdue_invoices'] ?? 0) > 0) {
            $score += 15;
            $factors[] = 'overdue_balance';
        }

        if (($student['open_guardian_issues'] ?? 0) > 0) {
            $score += 15;
            $factors[] = 'governance_issue';
        }

        $level = match (true) {
            $score >= 80 => 'Critical',
            $score >= 55 => 'High',
            $score >= 25 => 'Medium',
            default => 'Low',
        };

        return [
            'score' => min($score, 100),
            'level' => $level,
            'factors' => $factors,
        ];
    }

    public function calculateOperationalRisk(): array
    {
        $risk = $this->getRiskDashboard();
        $score = (int) (
            ($risk['at_risk_students'] ?? 0)
            + ($risk['overdue_payments'] ?? 0)
            + ($risk['open_health_alerts'] ?? 0)
            + ($risk['open_guardian_issues'] ?? 0)
        );

        $level = match (true) {
            $score >= 50 => 'Critical',
            $score >= 25 => 'High',
            $score >= 10 => 'Medium',
            default => 'Low',
        };

        return [
            'score' => $score,
            'level' => $level,
            'components' => $risk,
        ];
    }

    public function getAuditLogs(array $filters = []): array
    {
        $query = AuditLog::query()->orderByDesc('created_at');

        if (! empty($filters['module'])) {
            $query->where('module', $filters['module']);
        }

        if (! empty($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }

        if (! empty($filters['entity_type'])) {
            $query->where('entity_type', $filters['entity_type']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $items = $query->limit(100)->get()->map(fn (AuditLog $log): array => [
            'id' => $log->id,
            'eventType' => $log->event_type,
            'module' => $log->module,
            'entityType' => $log->entity_type,
            'entityId' => $log->entity_id,
            'actorName' => $log->actor_name,
            'actorRole' => $log->actor_role,
            'action' => $log->action,
            'beforeState' => $log->before_state,
            'afterState' => $log->after_state,
            'metadata' => $log->metadata,
            'ipAddress' => $log->ip_address,
            'userAgent' => $log->user_agent,
            'createdAt' => optional($log->created_at)->toIso8601String(),
        ])->all();

        return ['items' => $items];
    }

    public function getAuditLog(int $id): ?array
    {
        $log = AuditLog::query()->find($id);

        if (! $log) {
            return null;
        }

        return [
            'id' => $log->id,
            'eventType' => $log->event_type,
            'module' => $log->module,
            'entityType' => $log->entity_type,
            'entityId' => $log->entity_id,
            'actorName' => $log->actor_name,
            'actorRole' => $log->actor_role,
            'action' => $log->action,
            'beforeState' => $log->before_state,
            'afterState' => $log->after_state,
            'metadata' => $log->metadata,
            'ipAddress' => $log->ip_address,
            'userAgent' => $log->user_agent,
            'createdAt' => optional($log->created_at)->toIso8601String(),
        ];
    }

    public function getSecurityEvents(array $filters = []): array
    {
        $query = SecurityEvent::query()->orderByDesc('created_at');

        if (! empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }

        if (! empty($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }

        $items = $query->limit(100)->get()->map(fn (SecurityEvent $event): array => [
            'id' => $event->id,
            'eventType' => $event->event_type,
            'severity' => $event->severity,
            'userId' => $event->user_id,
            'ipAddress' => $event->ip_address,
            'description' => $event->description,
            'metadata' => $event->metadata,
            'resolvedAt' => optional($event->resolved_at)->toIso8601String(),
            'resolvedBy' => $event->resolved_by,
            'createdAt' => optional($event->created_at)->toIso8601String(),
            'updatedAt' => optional($event->updated_at)->toIso8601String(),
        ])->all();

        return ['items' => $items];
    }

    public function getSecurityEvent(int $id): ?array
    {
        $event = SecurityEvent::query()->find($id);

        if (! $event) {
            return null;
        }

        return [
            'id' => $event->id,
            'eventType' => $event->event_type,
            'severity' => $event->severity,
            'userId' => $event->user_id,
            'ipAddress' => $event->ip_address,
            'description' => $event->description,
            'metadata' => $event->metadata,
            'resolvedAt' => optional($event->resolved_at)->toIso8601String(),
            'resolvedBy' => $event->resolved_by,
            'createdAt' => optional($event->created_at)->toIso8601String(),
            'updatedAt' => optional($event->updated_at)->toIso8601String(),
        ];
    }

    public function resolveSecurityEvent(int $id, array $data = []): ?array
    {
        $event = SecurityEvent::query()->find($id);

        if (! $event) {
            return null;
        }

        $event->resolved_at = now();
        $event->resolved_by = $data['resolved_by'] ?? null;
        $event->save();

        return $this->getSecurityEvent($event->id);
    }

    public function getConfigurationHistory(array $filters = []): array
    {
        $query = AuditLog::query()->where('event_type', 'SETTINGS_UPDATED')->orderByDesc('created_at');

        $items = $query->limit(100)->get()->map(fn (AuditLog $log): array => [
            'id' => $log->id,
            'module' => $log->module,
            'entityType' => $log->entity_type,
            'actorName' => $log->actor_name,
            'beforeState' => $log->before_state,
            'afterState' => $log->after_state,
            'metadata' => $log->metadata,
            'createdAt' => optional($log->created_at)->toIso8601String(),
        ])->all();

        return ['items' => $items];
    }

    public function getAtRiskStudents(array $filters = []): array
    {
        return [];
    }

    public function getInvestigations(array $filters = []): array
    {
        return [
            'auditLogs' => $this->getAuditLogs($filters)['items'],
            'securityEvents' => $this->getSecurityEvents($filters)['items'],
            'risk' => $this->getRiskDashboard($filters),
        ];
    }

    public function recordAuditEvent(string $eventType, string $module, string $action, array $context = []): AuditLog
    {
        return AuditLog::query()->create([
            'event_type' => $eventType,
            'module' => $module,
            'entity_type' => $context['entity_type'] ?? null,
            'entity_id' => $context['entity_id'] ?? null,
            'actor_id' => $context['actor_id'] ?? null,
            'actor_name' => $context['actor_name'] ?? null,
            'actor_role' => $context['actor_role'] ?? null,
            'action' => $action,
            'before_state' => $context['before_state'] ?? null,
            'after_state' => $context['after_state'] ?? null,
            'metadata' => $context['metadata'] ?? null,
            'ip_address' => $context['ip_address'] ?? null,
            'user_agent' => $context['user_agent'] ?? null,
        ]);
    }

    public function recordSecurityEvent(string $eventType, string $severity, array $context = []): SecurityEvent
    {
        return SecurityEvent::query()->create([
            'event_type' => $eventType,
            'severity' => $severity,
            'user_id' => $context['user_id'] ?? null,
            'ip_address' => $context['ip_address'] ?? null,
            'description' => $context['description'] ?? '',
            'metadata' => $context['metadata'] ?? null,
        ]);
    }

    protected function countAuditLogs(array $where = [], ?string $dateFrom = null, ?string $dateTo = null): int
    {
        if (! Schema::hasTable('audit_logs')) {
            return 0;
        }

        $query = DB::table('audit_logs');

        foreach ($where as $column => $value) {
            $query->where($column, $value);
        }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        return (int) $query->count();
    }

    protected function countSecurityEvents(array $where = []): int
    {
        if (! Schema::hasTable('security_events')) {
            return 0;
        }

        $query = DB::table('security_events');

        foreach ($where as $column => $value) {
            $query->where($column, $value);
        }

        return (int) $query->count();
    }

    protected function topModules(): array
    {
        if (! Schema::hasTable('audit_logs')) {
            return [];
        }

        return DB::table('audit_logs')
            ->select('module', DB::raw('count(*) as total'))
            ->groupBy('module')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($row): array => [
                'module' => (string) $row->module,
                'total' => (int) $row->total,
            ])
            ->all();
    }

    protected function latestConfigurationChange(): ?string
    {
        if (! Schema::hasTable('audit_logs')) {
            return null;
        }

        return DB::table('audit_logs')
            ->where('event_type', 'SETTINGS_UPDATED')
            ->orderByDesc('created_at')
            ->value('created_at');
    }
}
