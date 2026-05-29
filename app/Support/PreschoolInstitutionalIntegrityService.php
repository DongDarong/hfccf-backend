<?php

namespace App\Support;

use App\Models\PreschoolLifecycleAuditLog;
use App\Models\PreschoolReportExportRecord;
use App\Models\PreschoolReportSnapshot;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Institutional integrity review stays read-only and audit-backed so admins
 * can inspect mismatches, override patterns, and historical anomalies without
 * mutating live Preschool records or inventing a second history store.
 */
class PreschoolInstitutionalIntegrityService
{
    public function __construct(
        private readonly PreschoolInstitutionalReconstructionService $reconstructionService,
        private readonly PreschoolLifecycleAuditService $auditService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function review(array $filters = []): array
    {
        $reconstruction = $this->reconstructionService->review($filters);
        $timeline = collect($reconstruction['timeline'] ?? []);
        $warnings = $this->buildSystemWarnings($reconstruction, $filters);
        $reviewKey = $this->systemReviewKey($filters);
        $trail = $this->reviewHistory($reviewKey);

        return [
            'overview' => [
                'totalEvents' => (int) ($reconstruction['overview']['totalAudits'] ?? 0),
                'blockedWrites' => (int) ($reconstruction['overview']['blockedWrites'] ?? 0),
                'overrideAttempts' => (int) ($reconstruction['overview']['overrideAttempts'] ?? 0),
                'overrideApprovals' => (int) ($reconstruction['overview']['overrideApprovals'] ?? 0),
                'exportEvents' => (int) ($reconstruction['overview']['exportEvents'] ?? 0),
                'snapshotCount' => (int) ($reconstruction['overview']['snapshotCount'] ?? 0),
                'reconstructionContexts' => (int) ($reconstruction['overview']['reconstructionContexts'] ?? 0),
            ],
            'warnings' => $warnings,
            'mismatches' => $warnings,
            'integrityWarnings' => $warnings,
            'riskScore' => $this->riskScoreFromWarnings($warnings, $reconstruction),
            'riskLevel' => $this->riskLevel($this->riskScoreFromWarnings($warnings, $reconstruction)),
            'timeline' => $timeline->take(60)->values()->all(),
            'retentionReview' => $reconstruction['retentionReview'] ?? [],
            'reviewKey' => $reviewKey,
            'reviewStatus' => $this->reviewStatusFromTrail($trail),
            'reviewTrail' => $trail,
        ];
    }

    /**
     * @param  array<string, mixed>  $resolvedContext
     * @param  array<string, mixed>  $filters
     */
    public function contextFromResolved(array $resolvedContext, array $filters = []): array
    {
        $summary = $resolvedContext['summary'] ?? [];
        $warnings = $this->buildContextWarnings($resolvedContext, $filters);
        $reviewKey = $resolvedContext['reviewKey'] ?? $this->contextReviewKey($resolvedContext, $filters);
        $trail = $this->reviewHistory($reviewKey);
        $timeline = collect($resolvedContext['timeline'] ?? []);

        return [
            'context' => $resolvedContext,
            'overview' => [
                'snapshotCount' => (int) Arr::get($summary, 'snapshotCount', 0),
                'auditCount' => (int) Arr::get($summary, 'auditCount', 0),
                'exportCount' => (int) Arr::get($summary, 'exportCount', 0),
                'assignmentCount' => (int) Arr::get($summary, 'assignmentCount', 0),
                'reportPeriodCount' => (int) Arr::get($summary, 'reportPeriodCount', 0),
                'academicYearCount' => (int) Arr::get($summary, 'academicYearCount', 0),
                'termCount' => (int) Arr::get($summary, 'termCount', 0),
            ],
            'warnings' => $warnings,
            'mismatches' => $warnings,
            'integrityWarnings' => $warnings,
            'riskScore' => $this->riskScoreFromWarnings($warnings, $resolvedContext),
            'riskLevel' => $this->riskLevel($this->riskScoreFromWarnings($warnings, $resolvedContext)),
            'timeline' => $timeline->take(50)->values()->all(),
            'retentionReview' => $resolvedContext['retentionReview'] ?? [],
            'reviewKey' => $reviewKey,
            'reviewStatus' => $this->reviewStatusFromTrail($trail),
            'reviewTrail' => $trail,
        ];
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $filters
     */
    public function compare(array $left, array $right, array $rows = [], array $filters = []): array
    {
        $warnings = $this->buildComparisonWarnings($left, $right, $rows, $filters);
        $reviewKey = $this->comparisonReviewKey($left, $right, $filters);
        $trail = $this->reviewHistory($reviewKey);

        return [
            'warnings' => $warnings,
            'mismatches' => $warnings,
            'integrityWarnings' => $warnings,
            'riskScore' => $this->riskScoreFromWarnings($warnings, [
                'summary' => [
                    'snapshotCount' => (int) (($left['summary']['snapshotCount'] ?? 0) + ($right['summary']['snapshotCount'] ?? 0)),
                    'auditCount' => (int) (($left['summary']['auditCount'] ?? 0) + ($right['summary']['auditCount'] ?? 0)),
                ],
            ]),
            'riskLevel' => $this->riskLevel($this->riskScoreFromWarnings($warnings, [
                'summary' => [
                    'snapshotCount' => (int) (($left['summary']['snapshotCount'] ?? 0) + ($right['summary']['snapshotCount'] ?? 0)),
                    'auditCount' => (int) (($left['summary']['auditCount'] ?? 0) + ($right['summary']['auditCount'] ?? 0)),
                ],
            ])),
            'reviewKey' => $reviewKey,
            'reviewStatus' => $this->reviewStatusFromTrail($trail),
            'reviewTrail' => $trail,
            'auditEvidence' => [
                'leftAuditCount' => (int) ($left['summary']['auditCount'] ?? 0),
                'rightAuditCount' => (int) ($right['summary']['auditCount'] ?? 0),
                'leftExportCount' => (int) ($left['summary']['exportCount'] ?? 0),
                'rightExportCount' => (int) ($right['summary']['exportCount'] ?? 0),
            ],
        ];
    }

    public function reviewHistory(string $reviewKey, int $limit = 10): array
    {
        if ($reviewKey === '') {
            return [];
        }

        return PreschoolLifecycleAuditLog::query()
            ->with(['actor'])
            ->where('entity_type', 'institutional_integrity_review')
            ->where('entity_id', $reviewKey)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (PreschoolLifecycleAuditLog $log): array => [
                'id' => $log->id,
                'actionType' => $log->action_type,
                'entityType' => $log->entity_type,
                'entityId' => $log->entity_id,
                'actor' => $this->userSnapshot($log->actor),
                'overrideReason' => $log->override_reason,
                'lockReason' => $log->lock_reason,
                'lockCode' => $log->lock_code,
                'note' => Arr::get($log->new_state ?? [], 'note'),
                'createdAt' => $log->created_at?->toISOString(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function buildSystemWarnings(array $reconstruction, array $filters = []): array
    {
        $overview = $reconstruction['overview'] ?? [];
        $retention = $reconstruction['retentionReview'] ?? [];
        $warnings = [];

        if ((int) ($overview['snapshotCount'] ?? 0) === 0 && ((int) ($overview['exportEvents'] ?? 0) > 0 || (int) ($overview['totalAudits'] ?? 0) > 0)) {
            $warnings[] = $this->warning(
                'missing_historical_records',
                'Critical',
                'reconstruction',
                'Historical records are missing while export or audit history exists.',
                'Review snapshot generation and archival coverage for the selected context.',
            );
        }

        if ((int) ($overview['overrideApprovals'] ?? 0) > (int) ($overview['snapshotCount'] ?? 0)) {
            $warnings[] = $this->warning(
                'override_pressure',
                'High',
                'audit',
                'Override approvals outnumber snapshots in the selected window.',
                'Inspect override reasons and confirm the underlying lifecycle guard policy.',
            );
        }

        if ((int) ($retention['oldSnapshots'] ?? 0) > 0 || (int) ($retention['oldExports'] ?? 0) > 0) {
            $warnings[] = $this->warning(
                'retention_pressure',
                'Medium',
                'retention',
                'Historical records have crossed the review window.',
                'Confirm retention review and archive handling remain policy-compliant.',
            );
        }

        if ((int) ($overview['blockedWrites'] ?? 0) > 0 && (int) ($overview['overrideAttempts'] ?? 0) === 0) {
            $warnings[] = $this->warning(
                'blocked_write_pattern',
                'Medium',
                'audit',
                'Blocked writes exist without corresponding override attempts.',
                'Verify whether staff are hitting locked contexts without an admin override path.',
            );
        }

        return $warnings;
    }

    /**
     * @param  array<string, mixed>  $resolvedContext
     * @param  array<string, mixed>  $filters
     */
    private function buildContextWarnings(array $resolvedContext, array $filters = []): array
    {
        $metrics = $resolvedContext['summary'] ?? [];
        $warnings = [];

        if ((int) Arr::get($metrics, 'snapshotCount', 0) === 0) {
            $warnings[] = $this->warning(
                'empty_context',
                'High',
                $resolvedContext['contextType'] ?? 'context',
                'The selected context resolved to no immutable snapshots.',
                'Check whether the academic year, term, report period, or snapshot selection is valid.',
            );
        }

        if ((int) Arr::get($metrics, 'exportCount', 0) > 0 && (int) Arr::get($metrics, 'snapshotCount', 0) === 0) {
            $warnings[] = $this->warning(
                'export_without_snapshot',
                'Critical',
                'export',
                'Exports exist without an immutable snapshot reference.',
                'Confirm export source integrity and regenerate from immutable data if needed.',
            );
        }

        if ((int) Arr::get($metrics, 'auditCount', 0) === 0 && (int) Arr::get($metrics, 'snapshotCount', 0) > 0) {
            $warnings[] = $this->warning(
                'audit_gap',
                'Medium',
                'audit',
                'Immutable snapshots exist but no matching audit trail was found for the selected context.',
                'Verify whether the lifecycle audit trail was filtered away or has not been recorded yet.',
            );
        }

        return $warnings;
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $filters
     */
    private function buildComparisonWarnings(array $left, array $right, array $rows = [], array $filters = []): array
    {
        $warnings = [];
        $criticalRows = collect($rows)->where('severity', 'CRITICAL')->count();
        $highRows = collect($rows)->where('severity', 'HIGH')->count();
        $mediumRows = collect($rows)->where('severity', 'MEDIUM')->count();

        if ($criticalRows > 0) {
            $warnings[] = $this->warning(
                'critical_diff',
                'Critical',
                'diff',
                'Frozen snapshot or export data changed across the selected institutional states.',
                'Review the frozen historical data immediately before relying on the comparison for governance reporting.',
            );
        }

        if (($left['summary']['snapshotCount'] ?? 0) !== ($right['summary']['snapshotCount'] ?? 0)) {
            $warnings[] = $this->warning(
                'snapshot_gap',
                'High',
                'snapshot',
                'Snapshot counts differ between the selected institutional states.',
                'Confirm whether the missing snapshots were archived, excluded, or never generated.',
            );
        }

        if (($left['summary']['studentCount'] ?? 0) !== ($right['summary']['studentCount'] ?? 0)) {
            $warnings[] = $this->warning(
                'student_anomaly',
                'High',
                'enrollment',
                'Student counts changed between the compared contexts.',
                'Inspect transfers, archived enrollments, and class membership changes for integrity concerns.',
            );
        }

        if (($left['summary']['assignmentCount'] ?? 0) !== ($right['summary']['assignmentCount'] ?? 0)) {
            $warnings[] = $this->warning(
                'assignment_mismatch',
                'Medium',
                'assignment',
                'Assignment totals changed between the compared contexts.',
                'Review class membership and teacher assignment history to ensure the change is expected.',
            );
        }

        if (($left['summary']['reportPeriodCount'] ?? 0) !== ($right['summary']['reportPeriodCount'] ?? 0)) {
            $warnings[] = $this->warning(
                'report_period_inconsistency',
                'High',
                'report_period',
                'Report-period coverage changed between the compared contexts.',
                'Verify that finalized or locked report periods are still represented in the frozen data set.',
            );
        }

        if (($left['summary']['exportCount'] ?? 0) !== ($right['summary']['exportCount'] ?? 0)) {
            $warnings[] = $this->warning(
                'export_integrity_issue',
                'Critical',
                'export',
                'Export totals differ between the compared institutional states.',
                'Confirm export provenance and immutable snapshot linkage before presenting the export history.',
            );
        }

        if ($highRows > 2 || $mediumRows > 4) {
            $warnings[] = $this->warning(
                'systemic_change',
                'High',
                'diff',
                'The change set crosses multiple governance-sensitive areas.',
                'Escalate the comparison for a broader institutional review.',
            );
        }

        return $warnings;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function warning(string $key, string $severity, string $source, string $message, string $reviewAction): array
    {
        return [
            'key' => $key,
            'severity' => $severity,
            'source' => $source,
            'message' => $message,
            'reviewAction' => $reviewAction,
            'reviewStatus' => 'open',
            'detectedAt' => now()->toISOString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function riskScoreFromWarnings(array $warnings, array $payload = []): int
    {
        $base = 0;

        foreach ($warnings as $warning) {
            $base += match (strtoupper((string) ($warning['severity'] ?? 'LOW'))) {
                'CRITICAL' => 25,
                'HIGH' => 15,
                'MEDIUM' => 8,
                default => 3,
            };
        }

        $base += (int) (($payload['overview']['overrideAttempts'] ?? $payload['summary']['overrideCount'] ?? 0) * 3);
        $base += (int) (($payload['overview']['blockedWrites'] ?? $payload['summary']['blockedWriteCount'] ?? 0) * 2);

        return min(100, max(0, $base));
    }

    private function riskLevel(int $score): string
    {
        return match (true) {
            $score >= 81 => 'Critical',
            $score >= 51 => 'High',
            $score >= 21 => 'Moderate',
            default => 'Low',
        };
    }

    private function reviewStatusFromTrail(array $trail): string
    {
        if (! $trail) {
            return 'open';
        }

        $latest = $trail[0]['actionType'] ?? '';

        return match ($latest) {
            'integrity_review.resolved', 'integrity_review.marked_reviewed' => 'resolved',
            'integrity_review.escalated' => 'escalated',
            'integrity_review.flagged' => 'flagged',
            default => 'open',
        };
    }

    private function systemReviewKey(array $filters = []): string
    {
        return 'system:'.sha1(json_encode($filters, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
    }

    /**
     * @param  array<string, mixed>  $resolvedContext
     * @param  array<string, mixed>  $filters
     */
    private function contextReviewKey(array $resolvedContext, array $filters = []): string
    {
        return (string) Arr::get($resolvedContext, 'reviewKey', 'context:'.sha1(json_encode([
            $resolvedContext['contextType'] ?? 'context',
            $resolvedContext['context'] ?? [],
            $filters,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: ''));
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     * @param  array<string, mixed>  $filters
     */
    private function comparisonReviewKey(array $left, array $right, array $filters = []): string
    {
        return 'diff:'.sha1(json_encode([
            $left['reviewKey'] ?? null,
            $right['reviewKey'] ?? null,
            Arr::get($filters, 'comparison_mode'),
            $filters,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function userSnapshot(mixed $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'firstName' => $user->first_name,
            'lastName' => $user->last_name,
            'displayName' => trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
            'roleCode' => $user->role_code,
        ];
    }
}
