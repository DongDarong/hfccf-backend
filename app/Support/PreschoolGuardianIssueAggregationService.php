<?php

namespace App\Support;

use App\Models\PreschoolGuardianGovernanceIssue;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

final class PreschoolGuardianIssueAggregationService
{
    /**
     * Dashboard summary metrics covering active issue counts, severity/priority
     * distribution, stale alerts, and recurring issue totals.
     */
    public function dashboardSummary(User $user): array
    {
        $this->ensureAdminAccess($user);

        $allActive = PreschoolGuardianGovernanceIssue::query()
            ->whereIn('status', PreschoolGuardianGovernanceStatus::ACTIVE_STATES)
            ->get();

        $total = PreschoolGuardianGovernanceIssue::query()->count();

        $staleIds = $allActive->filter(fn ($i) => $this->isStale($i))->pluck('id');

        return [
            'totalIssues' => $total,
            'activeIssues' => $allActive->count(),
            'resolvedIssues' => PreschoolGuardianGovernanceIssue::query()
                ->where('status', PreschoolGuardianGovernanceStatus::RESOLVED)->count(),
            'dismissedIssues' => PreschoolGuardianGovernanceIssue::query()
                ->where('status', PreschoolGuardianGovernanceStatus::DISMISSED)->count(),

            'bySeverity' => [
                'critical' => $allActive->where('severity', 'critical')->count(),
                'warning' => $allActive->where('severity', 'warning')->count(),
                'info' => $allActive->where('severity', 'info')->count(),
            ],
            'byPriority' => [
                'urgent' => $allActive->where('priority', PreschoolGuardianGovernancePriority::URGENT)->count(),
                'high' => $allActive->where('priority', PreschoolGuardianGovernancePriority::HIGH)->count(),
                'medium' => $allActive->where('priority', PreschoolGuardianGovernancePriority::MEDIUM)->count(),
                'low' => $allActive->where('priority', PreschoolGuardianGovernancePriority::LOW)->count(),
            ],
            'byStatus' => [
                'detected' => $allActive->where('status', PreschoolGuardianGovernanceStatus::DETECTED)->count(),
                'acknowledged' => $allActive->where('status', PreschoolGuardianGovernanceStatus::ACKNOWLEDGED)->count(),
                'assigned' => $allActive->where('status', PreschoolGuardianGovernanceStatus::ASSIGNED)->count(),
                'inReview' => $allActive->where('status', PreschoolGuardianGovernanceStatus::IN_REVIEW)->count(),
            ],

            'staleIssues' => $staleIds->count(),
            'recurringIssues' => $allActive->where('recurrence_count', '>', 0)->count(),
            'unassignedIssues' => $allActive->whereNull('assigned_to_user_id')->count(),
            'criticalUnresolved' => $allActive->where('severity', 'critical')->count(),

            'generatedAt' => now()->toISOString(),
        ];
    }

    /**
     * Paginated list of active issues that have exceeded their staleness threshold.
     * Thresholds: critical = 3 days, warning = 7 days, info = 14 days.
     */
    public function staleIssues(array $filters = []): LengthAwarePaginator
    {
        $criticalCutoff = now()->subDays(PreschoolGuardianGovernancePriority::staleThresholdDays('critical'));
        $warningCutoff = now()->subDays(PreschoolGuardianGovernancePriority::staleThresholdDays('warning'));
        $infoCutoff = now()->subDays(PreschoolGuardianGovernancePriority::staleThresholdDays('info'));

        $perPage = min(max((int) ($filters['per_page'] ?? 25), 5), 100);

        return PreschoolGuardianGovernanceIssue::query()
            ->with(['assignedTo'])
            ->whereIn('status', PreschoolGuardianGovernanceStatus::ACTIVE_STATES)
            ->where(function ($q) use ($criticalCutoff, $warningCutoff, $infoCutoff): void {
                $q->where(fn ($s) => $s->where('severity', 'critical')->where('detected_at', '<', $criticalCutoff))
                    ->orWhere(fn ($s) => $s->where('severity', 'warning')->where('detected_at', '<', $warningCutoff))
                    ->orWhere(fn ($s) => $s->where('severity', 'info')->where('detected_at', '<', $infoCutoff));
            })
            ->orderByRaw("CASE severity WHEN 'critical' THEN 1 WHEN 'warning' THEN 2 ELSE 3 END")
            ->orderBy('detected_at')
            ->paginate($perPage);
    }

    /**
     * Paginated list of active issues where recurrence_count > 0,
     * meaning the same problem has been detected more than once.
     */
    public function recurringIssues(array $filters = []): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 25), 5), 100);

        return PreschoolGuardianGovernanceIssue::query()
            ->with(['assignedTo'])
            ->whereIn('status', PreschoolGuardianGovernanceStatus::ACTIVE_STATES)
            ->where('recurrence_count', '>', 0)
            ->orderByDesc('recurrence_count')
            ->orderByRaw("CASE severity WHEN 'critical' THEN 1 WHEN 'warning' THEN 2 ELSE 3 END")
            ->paginate($perPage);
    }

    /**
     * Determine whether a single issue has exceeded its staleness threshold.
     */
    public function isStale(PreschoolGuardianGovernanceIssue $issue): bool
    {
        if (! PreschoolGuardianGovernanceStatus::isActive($issue->status)) {
            return false;
        }

        $thresholdDays = PreschoolGuardianGovernancePriority::staleThresholdDays($issue->severity);

        return $issue->detected_at->diffInDays(now()) >= $thresholdDays;
    }

    private function ensureAdminAccess(User $user): void
    {
        abort_unless(
            in_array($user->role_code, ['superadmin', 'adminpreschool'], true),
            Response::HTTP_FORBIDDEN,
            'Forbidden.',
        );
    }
}
